<?php

namespace Database\Factories;

use App\Models\Moment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Moment>
 */
class MomentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->firstName().' & '.fake()->firstName().' Wedding',
            'event_date' => fake()->date(),
        ];
    }
}
