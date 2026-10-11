<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePaperCardRequest;
use App\Models\Paper;
use App\Models\QrCard;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class PaperCardController extends Controller
{
    /**
     * Assign a session to the card the operator typed at the kiosk, or unassign it when the card is null.
     *
     * Kiosks send this only once the session's upload has succeeded and retry it until it does, so
     * any 4xx response is final: the card does not exist, or the paper was deleted by an admin.
     */
    public function update(UpdatePaperCardRequest $request, Paper $paper): JsonResponse
    {
        $label = $request->input('card');
        $card = $label === null ? null : QrCard::findByLabel($label);

        if ($label !== null && $card === null) {
            throw ValidationException::withMessages(['card' => "There is no card {$label}."]);
        }

        $paper->qrCard()->associate($card)->save();

        return response()->json([
            'data' => [
                'ref' => $paper->ref,
                'card' => $card?->label,
            ],
        ]);
    }
}
