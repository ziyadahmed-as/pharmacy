<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockReceiptLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_receipt_id',
        'product_id',
        'batch_no',
        'manufacturing_date',
        'expiry_date',
        'quantity',
        'unit_cost',
        'flag',
    ];

    protected function casts(): array
    {
        return [
            'manufacturing_date' => 'date',
            'expiry_date' => 'date',
            'quantity' => 'integer',
            'unit_cost' => 'integer',
        ];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(StockReceipt::class, 'stock_receipt_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
