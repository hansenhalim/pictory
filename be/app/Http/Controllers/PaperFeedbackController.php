<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FindsGuestPapers;
use App\Http\Requests\StorePaperFeedbackRequest;
use App\Models\Paper;
use Illuminate\Http\JsonResponse;

/**
 * Takes a guest's feedback on the photobooth, asked on the soft copy page once they download it.
 */
class PaperFeedbackController extends Controller
{
    use FindsGuestPapers;

    /**
     * Rate the paper behind a printed QR code.
     */
    public function storeForShortCode(StorePaperFeedbackRequest $request, string $code): JsonResponse
    {
        return $this->store($request, $this->findPaperByShortCode($code));
    }

    /**
     * Rate one of the papers assigned to a QR card.
     */
    public function storeForCard(StorePaperFeedbackRequest $request, string $uuid, int $paperId): JsonResponse
    {
        return $this->store($request, $this->findPaperOnCard($uuid, $paperId));
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
