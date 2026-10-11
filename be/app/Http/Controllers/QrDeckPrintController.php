<?php

namespace App\Http\Controllers;

use App\Models\QrCard;
use Illuminate\View\View;

class QrDeckPrintController extends Controller
{
    /**
     * Cards fit on an A3 sheet in 4 columns and 5 rows.
     */
    private const int CARDS_PER_SHEET = 20;

    /**
     * Show a deck's cards laid out on A3 sheets for the print shop.
     */
    public function show(int $deck): View
    {
        $cards = QrCard::query()->where('deck', $deck)->orderBy('number')->get();

        abort_if($cards->isEmpty(), 404);

        return view('qr-cards.print', [
            'deck' => $deck,
            'sheets' => $cards->chunk(self::CARDS_PER_SHEET),
        ]);
    }
}
