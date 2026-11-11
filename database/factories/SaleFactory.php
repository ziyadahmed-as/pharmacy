<?php

namespace Database\Factories;

use App\Models\Sale;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SaleFactory extends Factory
{
    protected $model = Sale::class;

    public function definition(): array
    {
        return [
            'receipt_no' => 'REC-'.fake()->unique()->numerify('2026-#####'),
            'shift_id' => Shift::factory(),
            'user_id' => User::factory(),
            'status' => 'completed',
            'total' => 10000,
            'idempotency_key' => (string) Str::uuid(),
            'reprint_count' => 0,
            'completed_at' => now(),
        ];
    }
}
