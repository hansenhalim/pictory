<?php

namespace App\Http\Controllers;

use App\Models\Paper;
use App\Models\QrCard;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SharedQrCardController extends Controller
{
    /**
     * Show the soft copies of every session assigned to a card, newest first, or a waiting page
     * until the first one arrives.
     */
    public function show(string $uuid): View
    {
        $card = QrCard::query()
            ->with(['papers' => fn (Builder $query) => $query->latest()->latest('id')])
            ->where('uuid', strtolower($uuid))
            ->firstOrFail();

        $disk = Storage::disk(config('filament.default_filesystem_disk'));
        $expiresAt = now()->addHour();

        return view('qr-cards.shared', [
            'sessions' => $card->papers
                ->values()
                ->map(fn (Paper $paper, int $index): array => [
                    'takenAt' => $paper->created_at,
                    'downloadName' => "pictory-{$card->label}-".($card->papers->count() - $index),
                    'paperUrl' => $disk->temporaryUrl($paper->file_path, $expiresAt),
                    'clipUrl' => $paper->clip_path ? $disk->temporaryUrl($paper->clip_path, $expiresAt) : null,
                    'photoUrls' => collect($paper->photos)
                        ->map(fn (array $photo): string => $disk->temporaryUrl($photo['file_path'], $expiresAt))
                        ->all(),
                ])
                ->all(),
        ]);
    }
}
