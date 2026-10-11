<?php

use App\Http\Controllers\Api\FrameController;
use App\Http\Controllers\Api\FrameImageController;
use App\Http\Controllers\Api\PaperCardController;
use App\Http\Controllers\Api\PaperController;
use App\Http\Controllers\Api\ShortCodeController;
use App\Http\Controllers\QrDeckPrintController;
use App\Http\Controllers\SharedPaperController;
use App\Http\Controllers\SharedQrCardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
 * Printed QR codes encode the URL in uppercase to fit QR alphanumeric mode, so both cases are routed.
 */
Route::get('/p/{code}', [SharedPaperController::class, 'show'])->whereAlphaNumeric('code')->name('papers.shared');
Route::get('/P/{code}', [SharedPaperController::class, 'show'])->whereAlphaNumeric('code');
Route::get('/c/{uuid}', [SharedQrCardController::class, 'show'])->whereUuid('uuid')->name('qr-cards.shared');
Route::get('/C/{uuid}', [SharedQrCardController::class, 'show'])->whereUuid('uuid');

Route::middleware('auth')->group(function () {
    Route::get('/admin/qr-cards/print/{deck}', [QrDeckPrintController::class, 'show'])->whereNumber('deck')->name('qr-cards.print');

    Route::get('/kiosk/{any?}', function () {
        $index = public_path('kiosk/index.html');

        abort_unless(is_file($index), 404, 'The kiosk has not been built. Run `npm run build` in fe/.');

        return response()->file($index, ['Cache-Control' => 'no-cache']);
    })->where('any', '.*')->name('kiosk');

    Route::prefix('api')->name('api.')->group(function () {
        Route::get('/frames', [FrameController::class, 'index'])->name('frames.index');
        Route::get('/frames/{frame}/image', [FrameImageController::class, 'show'])->name('frames.image');
        Route::post('/short-codes', [ShortCodeController::class, 'store'])->name('short-codes.store');
        Route::post('/papers', [PaperController::class, 'store'])->name('papers.store');
        Route::put('/papers/{paper:ref}/card', [PaperCardController::class, 'update'])->whereUuid('paper')->name('papers.card.update');
    });
});
