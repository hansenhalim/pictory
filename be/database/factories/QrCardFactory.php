<?php

namespace Database\Factories;

use App\Models\QrCard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QrCard>
 */
class QrCardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'deck' => fake()->unique()->numberBetween(1_000_000, 999_999_999),
            'number' => fake()->numberBetween(1, QrCard::DECK_SIZE),
            'uuid' => fake()->uuid(),
        ];
    }
}
