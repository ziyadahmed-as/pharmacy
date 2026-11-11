<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'method' => 'cash',
            'amount' => 10000,
            'reference' => null,
        ];
    }
}
