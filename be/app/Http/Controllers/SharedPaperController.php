<?php

namespace App\Http\Controllers;

use App\Models\ShortCode;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SharedPaperController extends Controller
{
    /**
     * Show the soft copy behind a printed QR code, or a waiting page while the kiosk is still uploading it.
     */
    public function show(string $code): View
    {
        $shortCode = ShortCode::query()
            ->with('paper')
            ->where('code', strtoupper($code))
            ->firstOrFail();

        $disk = Storage::disk(config('filament.default_filesystem_disk'));
        $expiresAt = now()->addHour();

        return view('papers.shared', [
            'code' => $shortCode->code,
            'paperUrl' => $shortCode->paper ? $disk->temporaryUrl($shortCode->paper->file_path, $expiresAt) : null,
            'clipUrl' => $shortCode->paper?->clip_path ? $disk->temporaryUrl($shortCode->paper->clip_path, $expiresAt) : null,
            'photoUrls' => collect($shortCode->paper?->photos)
                ->map(fn (array $photo): string => $disk->temporaryUrl($photo['file_path'], $expiresAt))
                ->all(),
        ]);
    }
}
