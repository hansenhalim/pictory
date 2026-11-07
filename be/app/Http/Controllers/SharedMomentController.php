<?php

namespace App\Http\Controllers;

use App\Models\Moment;
use App\Models\Paper;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SharedMomentController extends Controller
{
    /**
     * Show a moment's album: every paper in it, oldest first, four to a page. A link whose name
     * is out of date or mistyped is redirected to the current one.
     */
    public function show(string $slug): View|RedirectResponse
    {
        $moment = Moment::findBySlug($slug) ?? abort(404);

        if ($slug !== $moment->slug) {
            return redirect()->route('moments.shared', $moment->slug, 301);
        }

        $papers = $moment->papers()->oldest()->oldest('id')->get();
        $disk = $this->disk();
        $expiresAt = now()->addHour();

        return view('moments.album', [
            'moment' => $moment,
            'paperCount' => $papers->count(),
            'description' => $this->description($moment, $papers->count()),
            'coverUrl' => $this->coverUrl($moment),
            'pages' => $papers
                ->values()
                ->map(fn (Paper $paper, int $index): array => [
                    'number' => $index + 1,
                    'url' => route('moments.papers.shared', [$moment->slug, $paper->id]),
                    'paperUrl' => $disk->temporaryUrl($paper->file_path, $expiresAt),
                ])
                ->chunk(4)
                ->map(fn ($entries) => $entries->values()->all())
                ->all(),
        ]);
    }

    /**
     * Show one paper of a moment with its clip and photos to download, between links to the
     * papers before and after it.
     */
    public function showPaper(string $slug, int $paperId): View|RedirectResponse
    {
        $moment = Moment::findBySlug($slug) ?? abort(404);
        $paperIds = $moment->papers()->oldest()->oldest('id')->pluck('id');
        $index = $paperIds->search($paperId);

        abort_if($index === false, 404);

        if ($slug !== $moment->slug) {
            return redirect()->route('moments.papers.shared', [$moment->slug, $paperId], 301);
        }

        $paper = $moment->papers()->findOrFail($paperId);
        $disk = $this->disk();
        $expiresAt = now()->addHour();
        $number = $index + 1;

        return view('moments.paper', [
            'moment' => $moment,
            'number' => $number,
            'paperCount' => $paperIds->count(),
            'description' => "Photo {$this->padded($number)} of {$paperIds->count()}",
            'coverUrl' => $this->coverUrl($moment),
            'takenAt' => $paper->created_at,
            'downloadName' => (Str::slug($moment->name) ?: 'pictory').'-'.$this->padded($number),
            'paperUrl' => $disk->temporaryUrl($paper->file_path, $expiresAt),
            'clipUrl' => $paper->clip_path ? $disk->temporaryUrl($paper->clip_path, $expiresAt) : null,
            'photoUrls' => collect($paper->photos)
                ->map(fn (array $photo): string => $disk->temporaryUrl($photo['file_path'], $expiresAt))
                ->all(),
            'previousUrl' => $index > 0 ? route('moments.papers.shared', [$moment->slug, $paperIds[$index - 1]]) : null,
            'nextUrl' => $index < $paperIds->count() - 1 ? route('moments.papers.shared', [$moment->slug, $paperIds[$index + 1]]) : null,
        ]);
    }

    /**
     * Serve the link preview from this domain, since chat apps fetch it long after a temporary
     * storage URL would have expired.
     */
    public function cover(string $slug): StreamedResponse
    {
        $moment = Moment::findBySlug($slug) ?? abort(404);

        abort_unless($moment->cover_path && $this->disk()->exists($moment->cover_path), 404);

        return $this->disk()->response($moment->cover_path, 'cover.jpg', [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }

    private function disk(): FilesystemAdapter
    {
        return Storage::disk(config('filament.default_filesystem_disk'));
    }

    /**
     * The preview image's URL changes with the cover, so chat apps never show a stale one.
     */
    private function coverUrl(Moment $moment): string
    {
        if (! $moment->cover_path) {
            return asset('images/moment-cover.jpg');
        }

        return route('moments.cover', ['slug' => $moment->slug, 'v' => substr(md5($moment->cover_path), 0, 8)]);
    }

    /**
     * What the link preview says under the moment's name, e.g. "148 photos · 12 October 2026".
     */
    private function description(Moment $moment, int $paperCount): string
    {
        return collect([
            $paperCount > 0 ? $paperCount.' '.Str::plural('photo', $paperCount) : null,
            $moment->event_date?->format('j F Y'),
        ])->filter()->implode(' · ') ?: 'Pictory Photobooth';
    }

    private function padded(int $number): string
    {
        return str_pad((string) $number, 2, '0', STR_PAD_LEFT);
    }
}
