<?php

namespace Database\Factories;

use App\Models\Till;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Till>
 */
class TillFactory extends Factory
{
    protected $model = Till::class;

    public function definition(): array
    {
        return [
            'name' => 'Till '.fake()->unique()->numberBetween(1, 999),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
