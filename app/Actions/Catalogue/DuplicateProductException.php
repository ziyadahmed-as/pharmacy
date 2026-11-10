<?php

namespace App\Actions\Catalogue;

use App\Models\Product;
use Illuminate\Validation\ValidationException;

class DuplicateProductException extends ValidationException
{
    public Product $existingProduct;

    public static function withProduct(Product $product, string $message): self
    {
        $instance = parent::withMessages([
            'duplicate' => $message,
        ]);
        $instance->existingProduct = $product;

        return $instance;
    }
}
