<?php

namespace Database\Factories;

use App\Models\Paper;
use App\Models\ShortCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShortCode>
 */
class ShortCodeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => ShortCode::generateCode(),
        ];
    }

    /**
     * Indicate that the code has been printed on an uploaded paper.
     */
    public function used(): static
    {
        return $this->state(fn (array $attributes) => [
            'paper_id' => Paper::factory(),
        ]);
    }
}
