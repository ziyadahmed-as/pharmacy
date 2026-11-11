<?php

namespace Database\Factories;

use App\Models\Batch;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleLine;
use Illuminate\Database\Eloquent\Factories\Factory;

class SaleLineFactory extends Factory
{
    protected $model = SaleLine::class;

    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'product_id' => Product::factory(),
            'unit_id' => null,
            'batch_id' => Batch::factory(),
            'quantity' => 1,
            'unit_price' => 10000,
            'subtotal' => 10000,
            'is_fefo_override' => false,
            'override_reason' => null,
        ];
    }
}
