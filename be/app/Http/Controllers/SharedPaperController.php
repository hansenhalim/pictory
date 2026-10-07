<?php

namespace App\Http\Controllers;

use App\Models\Photo;
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
            ->with('paper.photos')
            ->where('code', strtoupper($code))
            ->firstOrFail();

        $disk = Storage::disk(config('filament.default_filesystem_disk'));
        $expiresAt = now()->addHour();

        return view('papers.shared', [
            'code' => $shortCode->code,
            'paperUrl' => $shortCode->paper ? $disk->temporaryUrl($shortCode->paper->file_path, $expiresAt) : null,
            'photoUrls' => $shortCode->paper?->photos
                ->map(fn (Photo $photo): string => $disk->temporaryUrl($photo->file_path, $expiresAt))
                ->all() ?? [],
        ]);
    }
}
