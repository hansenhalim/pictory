<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShortCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShortCodeController extends Controller
{
    /**
     * Reserve short codes so a kiosk can keep printing QR codes while offline.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'count' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json([
            'data' => ShortCode::reserve($validated['count'])->pluck('code'),
        ], 201);
    }
}
