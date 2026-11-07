<?php

namespace App\Jobs;

use App\Models\Moment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\File;
use Illuminate\Support\Facades\File as Files;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Render a moment's link preview: its cover paper, whole, over a blurred and darkened copy of
 * itself filling a 1200 × 630 JPEG, the shape chat apps show as a large preview card. Portrait
 * and landscape papers both fill the card this way, and the file stays small enough for WhatsApp
 * to show it.
 *
 * The job renders whatever the cover paper is by the time it runs, so it is safe to dispatch
 * more often than needed. A moment with no papers loses its cover.
 */
class RenderMomentCover implements ShouldQueue
{
    use Queueable;

    public const int WIDTH = 1200;

    public const int HEIGHT = 630;

    /** The space kept clear around the paper, so it reads as a print lying on the card. */
    private const int MARGIN = 40;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public int $timeout = 120;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Moment $moment) {}

    public function handle(): void
    {
        $moment = $this->moment->refresh();
        $coverPaper = $moment->resolveCoverPaper();
        $coverPath = $coverPaper ? Moment::coverPathFor($coverPaper) : null;
        $disk = Storage::disk(config('filament.default_filesystem_disk'));

        if ($coverPaper && $coverPath !== $moment->cover_path) {
            $directory = sys_get_temp_dir().'/pictory-moment-cover-'.Str::uuid();
            Files::ensureDirectoryExists($directory);

            try {
                $paperPath = "{$directory}/paper.png";
                $outputPath = "{$directory}/cover.jpg";
                Files::put($paperPath, $disk->get($coverPaper->file_path));

                Process::timeout($this->timeout - 30)
                    ->run($this->ffmpegCommand($paperPath, $outputPath))
                    ->throw();

                $disk->putFileAs(dirname($coverPath), new File($outputPath), basename($coverPath));
            } finally {
                Files::deleteDirectory($directory);
            }
        }

        $previousPath = $moment->cover_path;
        $moment->forceFill(['cover_path' => $coverPath])->saveQuietly();

        if ($previousPath && $previousPath !== $coverPath) {
            $disk->delete($previousPath);
        }
    }

    /**
     * @return list<string>
     */
    private function ffmpegCommand(string $paperPath, string $outputPath): array
    {
        $width = self::WIDTH;
        $height = self::HEIGHT;
        $paperWidth = $width - self::MARGIN * 2;
        $paperHeight = $height - self::MARGIN * 2;

        return [
            config('services.ffmpeg.binary'),
            '-v',
            'error',
            '-y',
            '-i',
            $paperPath,
            '-filter_complex',
            implode(';', [
                '[0:v]split=2[background][paper]',
                "[background]scale={$width}:{$height}:force_original_aspect_ratio=increase,crop={$width}:{$height},gblur=sigma=30,eq=brightness=-0.25[blurred]",
                "[paper]scale={$paperWidth}:{$paperHeight}:force_original_aspect_ratio=decrease[fitted]",
                '[blurred][fitted]overlay=(W-w)/2:(H-h)/2,format=yuvj420p[out]',
            ]),
            '-map',
            '[out]',
            '-frames:v',
            '1',
            '-q:v',
            '4',
            $outputPath,
        ];
    }
}
