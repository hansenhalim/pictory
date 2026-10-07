<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Frame;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FrameImageController extends Controller
{
    /**
     * Stream the frame image, which lives on a private disk.
     */
    public function show(Frame $frame): StreamedResponse
    {
        return Storage::disk(config('filament.default_filesystem_disk'))->response($frame->file_path, headers: [
            'Cache-Control' => 'private, max-age=31536000, immutable',
        ]);
    }
}
