<?php

use App\Http\Controllers\Api\FrameController;
use App\Http\Controllers\Api\FrameImageController;
use App\Http\Controllers\Api\PaperCardController;
use App\Http\Controllers\Api\PaperController;
use App\Http\Controllers\Api\ShortCodeController;
use App\Http\Controllers\PaperFeedbackController;
use App\Http\Controllers\PaperNoteController;
use App\Http\Controllers\QrDeckPrintController;
use App\Http\Controllers\SharedMomentController;
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

/*
 * From the soft copy page, guests rate the photobooth after downloading and leave a note for the
 * moment's hosts.
 */
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/p/{code}/feedback', [PaperFeedbackController::class, 'storeForShortCode'])->whereAlphaNumeric('code')->name('feedback.papers');
    Route::post('/c/{uuid}/papers/{paperId}/feedback', [PaperFeedbackController::class, 'storeForCard'])->whereUuid('uuid')->whereNumber('paperId')->name('feedback.qr-cards');
    Route::post('/p/{code}/note', [PaperNoteController::class, 'storeForShortCode'])->whereAlphaNumeric('code')->name('notes.papers');
    Route::post('/c/{uuid}/papers/{paperId}/note', [PaperNoteController::class, 'storeForCard'])->whereUuid('uuid')->whereNumber('paperId')->name('notes.qr-cards');
});

Route::prefix('/m/{slug}')->where(['slug' => '[A-Za-z0-9-]+'])->name('moments.')->group(function () {
    Route::get('/', [SharedMomentController::class, 'show'])->name('shared');
    Route::get('/cover.jpg', [SharedMomentController::class, 'cover'])->name('cover');
    Route::get('/{paperId}', [SharedMomentController::class, 'showPaper'])->whereNumber('paperId')->name('papers.shared');
});

Route::middleware('auth')->group(function () {
    Route::get('/admin/qr-cards/print/{deck}', [QrDeckPrintController::class, 'show'])->whereNumber('deck')->name('qr-cards.print');

    /*
     * The kiosk is cross-origin isolated so it can drive a DSLR with web-gphoto2 (see fe/vite.config.ts).
     */
    Route::get('/kiosk/{any?}', function () {
        $index = public_path('kiosk/index.html');

        abort_unless(is_file($index), 404, 'The kiosk has not been built. Run `npm run build` in fe/.');

        return response()->file($index, [
            'Cache-Control' => 'no-cache',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Cross-Origin-Embedder-Policy' => 'credentialless',
        ]);
    })->where('any', '.*')->name('kiosk');

    Route::prefix('api')->name('api.')->group(function () {
        Route::get('/frames', [FrameController::class, 'index'])->name('frames.index');
        Route::get('/frames/{frame}/image', [FrameImageController::class, 'show'])->name('frames.image');
        Route::post('/short-codes', [ShortCodeController::class, 'store'])->name('short-codes.store');
        Route::post('/papers', [PaperController::class, 'store'])->name('papers.store');
        Route::put('/papers/{paper:ref}/card', [PaperCardController::class, 'update'])->whereUuid('paper')->name('papers.card.update');
    });
});
