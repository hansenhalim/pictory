#!/usr/bin/env bash
#
# Installs the Pictory print agent (agent/ in the pictory repo) on a fresh Debian kiosk:
#
#   curl -fsSL https://pictory.id/agent.sh | sudo bash
#
# Fresh Debian ships wget rather than curl, so as root this works too:
#
#   wget -qO- https://pictory.id/agent.sh | bash
#
# It installs CUPS with Gutenprint, adds the USB DNP DS-RX1 as the CUPS printer "DS-RX1",
# installs the latest agent build from the rolling `agent-latest` GitHub release as a systemd
# service on 127.0.0.1:8001, and pre-grants https://pictory.id Local Network Access in
# Chrome and Chromium. Re-run it at any time to upgrade the agent or to add the printer
# after plugging it in.

set -euo pipefail

RELEASE_URL="https://github.com/hansenhalim/pictory/releases/download/agent-latest"
PRINTER="DS-RX1"
KIOSK_ORIGIN="https://pictory.id"
AGENT_URL="http://127.0.0.1:8001"

say() { printf '\033[1;34m==>\033[0m %s\n' "$*"; }
warn() { printf '\033[1;33mwarning:\033[0m %s\n' "$*" >&2; }
die() { printf '\033[1;31merror:\033[0m %s\n' "$*" >&2; exit 1; }

check_system() {
  [ "$(id -u)" -eq 0 ] || die "run as root: curl -fsSL https://pictory.id/agent.sh | sudo bash"
  command -v apt-get >/dev/null || die "this installer supports Debian and its derivatives only"
  command -v systemctl >/dev/null || die "systemd is required"

  case "$(dpkg --print-architecture)" in
    amd64) ARCH=amd64 ;;
    arm64) ARCH=arm64 ;;
    *) die "unsupported architecture $(dpkg --print-architecture); builds exist for amd64 and arm64" ;;
  esac
}

install_packages() {
  say "Installing CUPS and Gutenprint"
  export DEBIAN_FRONTEND=noninteractive
  apt-get update -qq
  apt-get install -y -qq --no-install-recommends \
    ca-certificates curl cups cups-client printer-driver-gutenprint >/dev/null
  systemctl enable --now cups >/dev/null
}

install_agent() {
  say "Downloading pictory-agent ($ARCH)"
  TMP_DIR="$(mktemp -d)"
  trap 'rm -rf "$TMP_DIR"' EXIT
  local tmp="$TMP_DIR"

  local file
  for file in "pictory-agent-linux-$ARCH" pictory-agent.service sha256sums.txt; do
    curl -fsSL --retry 3 -o "$tmp/$file" "$RELEASE_URL/$file" || die "could not download $file from $RELEASE_URL"
  done

  (cd "$tmp" && grep -E " (pictory-agent-linux-$ARCH|pictory-agent\.service)\$" sha256sums.txt | sha256sum -c --quiet -) \
    || die "checksum mismatch in the downloaded agent"

  say "Installing the pictory-agent service"
  install -m 755 "$tmp/pictory-agent-linux-$ARCH" /usr/local/bin/pictory-agent
  install -m 644 "$tmp/pictory-agent.service" /etc/systemd/system/pictory-agent.service
  systemctl daemon-reload
  systemctl enable pictory-agent >/dev/null 2>&1
  systemctl restart pictory-agent
}

# Prefers Gutenprint's own USB backend over CUPS' generic one: it reports the sheets left on
# the roll as marker levels, which the agent's /health passes on to the kiosk.
setup_printer() {
  if lpstat -p "$PRINTER" >/dev/null 2>&1; then
    say "CUPS printer $PRINTER already exists"
    return
  fi

  say "Looking for a USB $PRINTER"
  local devices uri model
  devices="$(timeout 60 lpinfo -v 2>/dev/null || true)"
  uri="$(awk '{print $2}' <<<"$devices" | grep -iE '^gutenprint[0-9.]*\+usb://dnp-dsrx1' | head -n1 || true)"
  if [ -z "$uri" ]; then
    uri="$(awk '{print $2}' <<<"$devices" | grep -iE '^usb://.*ds-?rx1' | head -n1 || true)"
  fi

  if [ -z "$uri" ]; then
    warn "no $PRINTER found on USB. Plug it in, switch it on, and run this installer again."
    return
  fi

  model="$(lpinfo -m 2>/dev/null | grep -iE '://dnp-dsrx1/expert ' | head -n1 | awk '{print $1}' || true)"
  [ -n "$model" ] || model="$(lpinfo -m 2>/dev/null | grep -iE 'dnp-dsrx1' | head -n1 | awk '{print $1}' || true)"
  [ -n "$model" ] || die "Gutenprint has no DS-RX1 driver; is printer-driver-gutenprint installed?"

  say "Adding $PRINTER at $uri"
  lpadmin -p "$PRINTER" -E -v "$uri" -m "$model" -o printer-error-policy=retry-job
  lpadmin -d "$PRINTER"
  cupsenable "$PRINTER"
  cupsaccept "$PRINTER"
}

# Lets the kiosk page call the agent on 127.0.0.1 without Chrome's Local Network Access prompt.
grant_local_network_access() {
  say "Granting $KIOSK_ORIGIN local network access in Chrome and Chromium"
  local dir
  for dir in /etc/chromium/policies/managed /etc/opt/chrome/policies/managed; do
    mkdir -p "$dir"
    printf '{ "LocalNetworkAccessAllowedForUrls": ["%s"] }\n' "$KIOSK_ORIGIN" >"$dir/pictory.json"
  done
}

report() {
  local health="" attempt
  for attempt in 1 2 3 4 5 6 7 8 9 10; do
    health="$(curl -fsS "$AGENT_URL/health" 2>/dev/null)" && break
    sleep 1
  done

  [ -n "$health" ] || die "the agent is not answering on $AGENT_URL; see: journalctl -u pictory-agent"

  say "Agent is running on $AGENT_URL"
  printf '    %s\n' "$health"

  if ! lpstat -p "$PRINTER" >/dev/null 2>&1; then
    warn "the agent is up but CUPS has no $PRINTER yet, so prints will fail until it is added"
  fi
}

main() {
  check_system
  install_packages
  install_agent
  setup_printer
  grant_local_network_access
  report
}

# Called last so a download cut off midway runs nothing.
main "$@"
