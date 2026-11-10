<?php

namespace Database\Factories;

use App\Models\Shift;
use App\Models\Till;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    protected $model = Shift::class;

    public function definition(): array
    {
        return [
            'till_id' => Till::factory(),
            'user_id' => User::factory(),
            'opened_at' => now(),
            'opening_float' => 50000, // 500.00 ETB in santim
            'status' => 'open',
            'closed_at' => null,
            'expected_cash_per_payment_method' => null,
            'counted_cash_per_payment_method' => null,
            'variance' => null,
            'variance_reason' => null,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attrs) => [
            'status' => 'closed',
            'closed_at' => now(),
            'expected_cash_per_payment_method' => ['cash' => 100000],
            'counted_cash_per_payment_method' => ['cash' => 100000],
            'variance' => 0,
            'variance_reason' => null,
        ]);
    }
}
