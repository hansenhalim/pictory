<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaperFeedbackRequest;
use App\Models\Paper;
use App\Models\QrCard;
use App\Models\ShortCode;
use Illuminate\Http\JsonResponse;

/**
 * Takes a guest's feedback on the photobooth, asked on the soft copy page once they download it.
 *
 * The link a guest scanned is their only access, so feedback is taken through that same link.
 */
class PaperFeedbackController extends Controller
{
    /**
     * Rate the paper behind a printed QR code.
     */
    public function storeForShortCode(StorePaperFeedbackRequest $request, string $code): JsonResponse
    {
        $paper = ShortCode::query()
            ->with('paper')
            ->where('code', strtoupper($code))
            ->firstOrFail()
            ->paper ?? abort(404);

        return $this->store($request, $paper);
    }

    /**
     * Rate one of the papers assigned to a QR card.
     */
    public function storeForCard(StorePaperFeedbackRequest $request, string $uuid, int $paperId): JsonResponse
    {
        $paper = QrCard::query()
            ->where('uuid', strtolower($uuid))
            ->firstOrFail()
            ->papers()
            ->findOrFail($paperId);

        return $this->store($request, $paper);
    }

    /**
     * Record the feedback, or answer 409 when another guest has already rated the paper.
     */
    private function store(StorePaperFeedbackRequest $request, Paper $paper): JsonResponse
    {
        $recorded = $paper->rate($request->integer('rating'), $request->string('comment')->trim()->toString() ?: null);

        return response()->json(['data' => ['rated' => true]], $recorded ? 201 : 409);
    }
}
