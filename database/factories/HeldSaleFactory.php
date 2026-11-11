<?php

namespace Database\Factories;

use App\Models\HeldSale;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class HeldSaleFactory extends Factory
{
    protected $model = HeldSale::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'shift_id' => Shift::factory(),
            'reference' => 'Customer Hold #'.fake()->numberBetween(1, 999),
            'cart' => [],
            'total' => 2500,
            'status' => 'held',
            'held_at' => now(),
            'resumed_at' => null,
            'discarded_at' => null,
            'discard_reason' => null,
        ];
    }
}

