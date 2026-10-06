<?php

namespace App\Models;

use Database\Factories\FrameFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property list<list<array{x: int, y: int, width: int, height: int}>> $slots One entry per photo, each listing every placement of that photo on the paper.
 * @property list<array{x: int, y: int, size: int}> $qr_codes Every placement of the QR code on the paper; empty when the frame has none.
 *
 * A QR code is drawn on top of the frame as a white square of `size` pixels, including a
 * 4-module quiet zone around black modules encoded with error correction level Q.
 */
#[Fillable(['file_path', 'slots', 'qr_codes'])]
class Frame extends Model
{
    /** @use HasFactory<FrameFactory> */
    use HasFactory;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'qr_codes' => '[]',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'slots' => 'array',
            'qr_codes' => 'array',
        ];
    }
}
