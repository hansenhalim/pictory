<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Paper;
use App\Models\QrCard;
use App\Models\ShortCode;

/**
 * Find the paper a guest is acting on through the link they scanned, which is their only access.
 */
trait FindsGuestPapers
{
    /**
     * The paper behind a printed QR code.
     */
    protected function findPaperByShortCode(string $code): Paper
    {
        return ShortCode::query()
            ->with('paper')
            ->where('code', strtoupper($code))
            ->firstOrFail()
            ->paper ?? abort(404);
    }

    /**
     * One of the papers assigned to a QR card.
     */
    protected function findPaperOnCard(string $uuid, int $paperId): Paper
    {
        return QrCard::query()
            ->where('uuid', strtolower($uuid))
            ->firstOrFail()
            ->papers()
            ->findOrFail($paperId);
    }
}
