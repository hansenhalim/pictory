<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FrameResource;
use App\Models\Frame;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FrameController extends Controller
{
    /**
     * List every frame a kiosk can offer.
     */
    public function index(): AnonymousResourceCollection
    {
        return FrameResource::collection(Frame::query()->oldest('id')->get());
    }
}
