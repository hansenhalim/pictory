<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FindsGuestPapers;
use App\Http\Requests\StorePaperNoteRequest;
use App\Models\Paper;
use Illuminate\Http\JsonResponse;

/**
 * Takes a guest's note for the moment's hosts, written on the soft copy page.
 */
class PaperNoteController extends Controller
{
    use FindsGuestPapers;

    /**
     * Leave a note on the paper behind a printed QR code.
     */
    public function storeForShortCode(StorePaperNoteRequest $request, string $code): JsonResponse
    {
        return $this->store($request, $this->findPaperByShortCode($code));
    }

    /**
     * Leave a note on one of the papers assigned to a QR card.
     */
    public function storeForCard(StorePaperNoteRequest $request, string $uuid, int $paperId): JsonResponse
    {
        return $this->store($request, $this->findPaperOnCard($uuid, $paperId));
    }

    /**
     * Record the note, or answer 409 with the note another guest left first, so the page can show it.
     */
    private function store(StorePaperNoteRequest $request, Paper $paper): JsonResponse
    {
        $recorded = $paper->leaveNote($request->string('note')->trim()->toString());

        return response()->json(['data' => ['note' => $paper->note]], $recorded ? 201 : 409);
    }
}
