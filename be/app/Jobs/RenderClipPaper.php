<?php

namespace App\Jobs;

use App\Models\Paper;
use App\Models\Photo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\File;
use Illuminate\Support\Facades\File as Files;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Render a session's clip paper: the soft copy as a video. Every slot plays its behind-the-scenes
 * clip at once, cover-fitted into each of its placements under the frame. As each clip ends it
 * fades through white into its photo, and the finished paper holds still for a moment at the end.
 *
 * The clips differ in length, since the kiosk counts each slot down a little longer than the
 * one before, so the slots turn into their photos one after another.
 */
class RenderClipPaper implements ShouldQueue
{
    use Queueable;

    /** How long each clip takes to fade through white into its photo, overlapping its end. */
    private const float FADE_SECONDS = 0.5;

    /** How long the paper holds still after the last slot has turned into its photo. */
    private const float HOLD_SECONDS = 2;

    private const int FRAME_RATE = 30;

    /** The clip paper is scaled down to fit this on its longer side. */
    private const int MAX_SIZE = 1920;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public int $timeout = 300;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Paper $paper) {}

    public function handle(): void
    {
        $paper = $this->paper->load(['frame', 'photos']);

        // The frame may have been deleted or edited since the session was shot.
        if (! $paper->canRenderClipPaper()) {
            return;
        }

        $disk = Storage::disk(config('filament.default_filesystem_disk'));
        $directory = sys_get_temp_dir().'/pictory-clip-paper-'.Str::uuid();
        Files::ensureDirectoryExists($directory);

        try {
            $framePath = "{$directory}/frame.png";
            Files::put($framePath, $disk->get($paper->frame->file_path));

            $slots = $paper->photos
                ->sortBy('id')
                ->values()
                ->map(function (Photo $photo, int $index) use ($disk, $directory, $paper): array {
                    $clipPath = "{$directory}/clip-{$index}.mp4";
                    $photoPath = "{$directory}/photo-{$index}.jpg";
                    Files::put($clipPath, $disk->get($photo->clip_path));
                    Files::put($photoPath, $disk->get($photo->file_path));

                    return [
                        'clip' => $clipPath,
                        'photo' => $photoPath,
                        'clipSeconds' => $this->durationOf($clipPath),
                        'placements' => $paper->frame->slots[$index],
                    ];
                })
                ->all();

            $outputPath = "{$directory}/clip-paper.mp4";

            Process::timeout($this->timeout - 30)
                ->run($this->ffmpegCommand($framePath, $slots, $outputPath))
                ->throw();

            $previousPath = $paper->clip_path;
            $paper->update(['clip_path' => $disk->putFile('clip-papers', new File($outputPath))]);

            if ($previousPath) {
                $disk->delete($previousPath);
            }
        } finally {
            Files::deleteDirectory($directory);
        }
    }

    private function durationOf(string $path): float
    {
        return (float) Process::run([
            config('services.ffmpeg.ffprobe_binary'),
            '-v', 'error',
            '-show_entries', 'format=duration',
            '-of', 'csv=p=0',
            $path,
        ])->throw()->output();
    }

    /**
     * Build one ffmpeg run that composes the whole clip paper. Inputs are a white background,
     * the frame, then each slot's clip and its photo.
     *
     * @param  list<array{clip: string, photo: string, clipSeconds: float, placements: list<array{x: int, y: int, width: int, height: int}>}>  $slots
     * @return list<string>
     */
    private function ffmpegCommand(string $framePath, array $slots, string $outputPath): array
    {
        [$width, $height] = getimagesize($framePath);
        $seconds = $this->number(max(array_column($slots, 'clipSeconds')) + self::HOLD_SECONDS);
        $still = ['-loop', '1', '-framerate', (string) self::FRAME_RATE, '-t', $seconds];

        $inputs = [
            '-f', 'lavfi', '-i', "color=white:s={$width}x{$height}:r=".self::FRAME_RATE.":d={$seconds}",
            ...$still, '-i', $framePath,
        ];
        $filters = [];
        $overlays = [];

        foreach ($slots as $index => $slot) {
            $clipInput = 2 + $index * 2;
            $photoInput = $clipInput + 1;
            $inputs = [...$inputs, '-i', $slot['clip'], ...$still, '-i', $slot['photo']];

            // Every placement of a photo shares its shape, so the slot is composed at the size
            // of the first and scaled for any other.
            ['width' => $slotWidth, 'height' => $slotHeight] = $slot['placements'][0];
            $fit = $this->coverFilter($slotWidth, $slotHeight);
            $fadeOffset = $this->number(max(0, $slot['clipSeconds'] - self::FADE_SECONDS));

            $filters[] = "[{$clipInput}:v]{$fit}[clip{$index}]";
            $filters[] = "[{$photoInput}:v]{$fit}[photo{$index}]";
            $filters[] = "[clip{$index}][photo{$index}]xfade=transition=fadewhite:duration=".self::FADE_SECONDS
                .":offset={$fadeOffset},trim=duration={$seconds}"
                .','.$this->splitFilter(count($slot['placements']), "slot{$index}_");

            foreach ($slot['placements'] as $placementIndex => $placement) {
                $label = "slot{$index}_{$placementIndex}";

                if ($placement['width'] !== $slotWidth || $placement['height'] !== $slotHeight) {
                    $filters[] = "[{$label}]scale={$placement['width']}:{$placement['height']},setsar=1[{$label}s]";
                    $label .= 's';
                }

                $overlays[] = ['label' => $label, 'x' => $placement['x'], 'y' => $placement['y']];
            }
        }

        $previous = '0:v';

        foreach ($overlays as $index => $overlay) {
            $filters[] = "[{$previous}][{$overlay['label']}]overlay=x={$overlay['x']}:y={$overlay['y']}[layer{$index}]";
            $previous = "layer{$index}";
        }

        $scale = min(1, self::MAX_SIZE / max($width, $height));
        $outputWidth = (int) floor($width * $scale / 2) * 2;
        $outputHeight = (int) floor($height * $scale / 2) * 2;
        $filters[] = "[{$previous}][1:v]overlay=0:0,scale={$outputWidth}:{$outputHeight},format=yuv420p[out]";

        return [
            config('services.ffmpeg.binary'),
            '-v', 'error',
            '-y',
            ...$inputs,
            '-filter_complex', implode(';', $filters),
            '-map', '[out]',
            '-t', $seconds,
            '-r', (string) self::FRAME_RATE,
            '-c:v', 'libx264',
            '-preset', 'medium',
            '-crf', '20',
            '-pix_fmt', 'yuv420p',
            '-movflags', '+faststart',
            '-an',
            $outputPath,
        ];
    }

    /** Fill a box of this size without stretching, like CSS `object-fit: cover`, at a constant frame rate. */
    private function coverFilter(int $width, int $height): string
    {
        return 'fps='.self::FRAME_RATE
            .",scale={$width}:{$height}:force_original_aspect_ratio=increase,crop={$width}:{$height},setsar=1,format=yuv420p";
    }

    /** Copy the stream once per placement, labelled with the prefix and the placement's index. */
    private function splitFilter(int $count, string $prefix): string
    {
        $labels = implode('', array_map(fn (int $index): string => "[{$prefix}{$index}]", range(0, $count - 1)));

        return "split={$count}{$labels}";
    }

    private function number(float $value): string
    {
        return rtrim(rtrim(sprintf('%.3F', $value), '0'), '.');
    }
}
