<?php

namespace Database\Factories;

use App\Models\Frame;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Frame>
 */
class FrameFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'file_path' => 'frames/'.Str::random(40).'.png',
            'slots' => [
                [['x' => 29, 'y' => 40, 'width' => 600, 'height' => 400]],
                [['x' => 29, 'y' => 440, 'width' => 600, 'height' => 400]],
                [['x' => 29, 'y' => 840, 'width' => 600, 'height' => 400]],
            ],
            'cut' => false,
        ];
    }

    /**
     * Indicate that the paper carries a QR code at the bottom of each strip.
     */
    public function withQrCodes(): static
    {
        return $this->state(fn (array $attributes) => [
            'qr_codes' => [
                ['x' => 269, 'y' => 1280, 'size' => 120],
                ['x' => 869, 'y' => 1280, 'size' => 120],
            ],
        ]);
    }

    /**
     * Indicate that each photo is printed twice, side by side, as on a 2R strip cut from 4R.
     */
    public function duplicated(): static
    {
        return $this->state(fn (array $attributes) => [
            'slots' => [
                [['x' => 29, 'y' => 40, 'width' => 600, 'height' => 400], ['x' => 629, 'y' => 40, 'width' => 600, 'height' => 400]],
                [['x' => 29, 'y' => 440, 'width' => 600, 'height' => 400], ['x' => 629, 'y' => 440, 'width' => 600, 'height' => 400]],
                [['x' => 29, 'y' => 840, 'width' => 600, 'height' => 400], ['x' => 629, 'y' => 840, 'width' => 600, 'height' => 400]],
            ],
            'cut' => true,
        ]);
    }
}
