@php
    /**
     * A deck's cards laid out for the print shop: 90 × 55 mm cards, edge to edge in a 4 × 5 grid
     * centered on each A3 landscape sheet. The QR codes are drawn in the browser.
     *
     * @var int $deck
     * @var \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, \App\Models\QrCard>> $sheets
     */
@endphp

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Deck {{ $deck }}</title>
        <style>
            @page {
                size: A3 landscape;
                margin: 0;
            }

            * {
                box-sizing: border-box;
                margin: 0;
            }

            body {
                background: #e5e5e5;
                color: #000;
                font-family: ui-sans-serif, system-ui, sans-serif;
            }

            .sheet {
                display: grid;
                grid-template-columns: repeat(4, 90mm);
                grid-template-rows: repeat(5, 55mm);
                place-content: center;
                width: 420mm;
                height: 297mm;
                margin: 10mm auto;
                background: #fff;
                break-after: page;
            }

            .card {
                display: flex;
                align-items: center;
                padding: 0 5mm;
            }

            .qr {
                flex: none;
                width: 45mm;
                height: 45mm;
            }

            .qr svg {
                display: block;
                width: 100%;
                height: 100%;
            }

            .label {
                flex: 1;
                font-size: 9mm;
                font-variant-numeric: tabular-nums;
                font-weight: 700;
                text-align: center;
                white-space: nowrap;
            }

            @media print {
                body {
                    background: none;
                }

                .sheet {
                    margin: 0;
                }
            }
        </style>
        @vite(['resources/js/qr-cards.js'])
    </head>
    <body>
        @foreach ($sheets as $cards)
            <section class="sheet">
                @foreach ($cards as $card)
                    <div class="card">
                        <div class="qr" data-qr-url="{{ $card->qrCodeUrl() }}"></div>
                        <p class="label">{{ $card->label }}</p>
                    </div>
                @endforeach
            </section>
        @endforeach
    </body>
</html>
