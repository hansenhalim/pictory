// Command pictory-agent is the kiosk's local print service.
//
// The kiosk page renders each paper in the browser and posts the PNG here. The agent
// hands it to CUPS with `lp`, choosing the media from whether the frame is cut into
// strips, and putting single prints ahead of bulk ones in the queue.
//
// Endpoints:
//
//	GET  /health  reports whether the printer is known to CUPS
//	POST /print   multipart: paper (PNG), copies (1-20), cut (bool)
//
// It listens on loopback only and refuses requests from origins outside -origins, since
// any web page can send a form POST to localhost.
//
// Build for the kiosk with:
//
//	GOOS=linux GOARCH=amd64 go build -o dist/pictory-agent .
package main

import (
	"bytes"
	"context"
	"encoding/json"
	"errors"
	"flag"
	"fmt"
	"io"
	"log/slog"
	"net/http"
	"os"
	"os/exec"
	"strconv"
	"strings"
	"time"
)

const (
	maxPaperBytes = 32 << 20
	maxCopies     = 20

	mediaUncut = "w288h432"
	mediaCut   = "w288h432-div2"
)

// runner executes a command and returns its combined output. It is swapped out in dry-run mode.
type runner func(ctx context.Context, name string, args ...string) ([]byte, error)

type server struct {
	printer string
	origins map[string]bool
	run     runner
	log     *slog.Logger
}

func main() {
	addr := flag.String("addr", env("PICTORY_AGENT_ADDR", "127.0.0.1:8001"), "address to listen on")
	printer := flag.String("printer", env("PICTORY_AGENT_PRINTER", "DS-RX1"), "CUPS printer name")
	origins := flag.String("origins", env("PICTORY_AGENT_ORIGINS", "https://pictory.id"), "comma-separated origins allowed to print")
	dryRun := flag.Bool("dry-run", os.Getenv("PICTORY_AGENT_DRY_RUN") == "true", "log lp commands instead of running them")
	flag.Parse()

	logger := slog.New(slog.NewTextHandler(os.Stderr, nil))

	s := &server{
		printer: *printer,
		origins: parseOrigins(*origins),
		run:     execRunner,
		log:     logger,
	}

	if *dryRun {
		s.run = dryRunner(logger)
	}

	mux := http.NewServeMux()
	mux.HandleFunc("GET /health", s.health)
	mux.HandleFunc("POST /print", s.print)

	httpServer := &http.Server{
		Addr:              *addr,
		Handler:           s.cors(mux),
		ReadHeaderTimeout: 10 * time.Second,
	}

	logger.Info("listening", "addr", *addr, "printer", *printer, "origins", *origins, "dry_run", *dryRun)

	if err := httpServer.ListenAndServe(); err != nil {
		logger.Error("server stopped", "err", err)
		os.Exit(1)
	}
}

// cors lets the configured kiosk origins call the agent, and rejects every other origin
// outright: a cross-origin form POST runs even when the browser hides the response.
func (s *server) cors(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		origin := r.Header.Get("Origin")

		if origin == "" {
			next.ServeHTTP(w, r)
			return
		}

		if !s.origins[origin] {
			writeError(w, http.StatusForbidden, "origin not allowed")
			return
		}

		header := w.Header()
		header.Set("Access-Control-Allow-Origin", origin)
		header.Add("Vary", "Origin")

		if r.Method == http.MethodOptions {
			header.Set("Access-Control-Allow-Methods", "GET, POST")
			header.Set("Access-Control-Allow-Headers", "Content-Type")
			header.Set("Access-Control-Max-Age", "600")

			if r.Header.Get("Access-Control-Request-Private-Network") == "true" {
				header.Set("Access-Control-Allow-Private-Network", "true")
			}

			w.WriteHeader(http.StatusNoContent)
			return
		}

		next.ServeHTTP(w, r)
	})
}

func (s *server) health(w http.ResponseWriter, r *http.Request) {
	output, err := s.run(r.Context(), "lpstat", "-p", s.printer)

	writeJSON(w, http.StatusOK, map[string]any{
		"printer": s.printer,
		"ok":      err == nil,
		"status":  strings.TrimSpace(string(output)),
	})
}

func (s *server) print(w http.ResponseWriter, r *http.Request) {
	r.Body = http.MaxBytesReader(w, r.Body, maxPaperBytes)

	if err := r.ParseMultipartForm(maxPaperBytes); err != nil {
		writeError(w, http.StatusBadRequest, "invalid multipart form")
		return
	}
	defer r.MultipartForm.RemoveAll()

	copies, err := strconv.Atoi(r.FormValue("copies"))
	if err != nil || copies < 1 || copies > maxCopies {
		writeError(w, http.StatusUnprocessableEntity, fmt.Sprintf("copies must be between 1 and %d", maxCopies))
		return
	}

	cut := false
	if value := r.FormValue("cut"); value != "" {
		if cut, err = strconv.ParseBool(value); err != nil {
			writeError(w, http.StatusUnprocessableEntity, "cut must be a boolean")
			return
		}
	}

	paper, _, err := r.FormFile("paper")
	if err != nil {
		writeError(w, http.StatusUnprocessableEntity, "paper is required")
		return
	}
	defer paper.Close()

	path, err := savePNG(paper)
	if errors.Is(err, errNotPNG) {
		writeError(w, http.StatusUnprocessableEntity, "paper must be a PNG")
		return
	}
	if err != nil {
		s.log.Error("saving paper", "err", err)
		writeError(w, http.StatusInternalServerError, "could not save paper")
		return
	}
	defer os.Remove(path)

	output, err := s.run(r.Context(), "lp", lpArgs(s.printer, path, copies, cut)...)
	if err != nil {
		s.log.Error("printing", "err", err, "output", string(output))
		writeError(w, http.StatusBadGateway, strings.TrimSpace("lp failed: "+string(output)))
		return
	}

	s.log.Info("printed", "copies", copies, "cut", cut, "output", strings.TrimSpace(string(output)))

	writeJSON(w, http.StatusOK, map[string]any{
		"ok":     true,
		"output": strings.TrimSpace(string(output)),
	})
}

// lpArgs builds the lp command line. Single prints get top priority so a customer's
// first print is not stuck behind someone else's batch.
func lpArgs(printer, path string, copies int, cut bool) []string {
	media := mediaUncut
	if cut {
		media = mediaCut
	}

	priority := 100
	if copies > 1 {
		priority = 1
	}

	return []string{
		"-d", printer,
		"-o", "media=" + media,
		"-n", strconv.Itoa(copies),
		"-q", strconv.Itoa(priority),
		path,
	}
}

var errNotPNG = errors.New("not a PNG")

// savePNG writes the upload to a temporary file after checking it really is a PNG.
func savePNG(paper io.Reader) (string, error) {
	head := make([]byte, 512)
	n, err := io.ReadFull(paper, head)
	if err != nil && !errors.Is(err, io.ErrUnexpectedEOF) {
		return "", errNotPNG
	}
	head = head[:n]

	if http.DetectContentType(head) != "image/png" {
		return "", errNotPNG
	}

	file, err := os.CreateTemp("", "pictory-*.png")
	if err != nil {
		return "", err
	}
	defer file.Close()

	if _, err := io.Copy(file, io.MultiReader(bytes.NewReader(head), paper)); err != nil {
		os.Remove(file.Name())
		return "", err
	}

	return file.Name(), nil
}

func execRunner(ctx context.Context, name string, args ...string) ([]byte, error) {
	ctx, cancel := context.WithTimeout(ctx, 30*time.Second)
	defer cancel()

	return exec.CommandContext(ctx, name, args...).CombinedOutput()
}

func dryRunner(logger *slog.Logger) runner {
	return func(_ context.Context, name string, args ...string) ([]byte, error) {
		logger.Info("dry run", "command", name, "args", args)

		return []byte("dry run: " + name + " " + strings.Join(args, " ")), nil
	}
}

func parseOrigins(value string) map[string]bool {
	origins := map[string]bool{}

	for origin := range strings.SplitSeq(value, ",") {
		if origin = strings.TrimSpace(origin); origin != "" {
			origins[strings.TrimSuffix(origin, "/")] = true
		}
	}

	return origins
}

func env(key, fallback string) string {
	if value := os.Getenv(key); value != "" {
		return value
	}

	return fallback
}

func writeJSON(w http.ResponseWriter, status int, body any) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	json.NewEncoder(w).Encode(body)
}

func writeError(w http.ResponseWriter, status int, message string) {
	writeJSON(w, status, map[string]any{"ok": false, "message": message})
}
