<?php

namespace App\Http\Resources;

use App\Models\Frame;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @mixin Frame
 */
class FrameResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'image_url' => route('api.frames.image', ['frame' => $this->resource, 'v' => $this->updated_at?->timestamp], absolute: false),
            'slots' => $this->slots,
            'qr_codes' => $this->qr_codes,
            'cut' => $this->cut,
            'updated_at' => $this->updated_at,
        ];
    }
}
