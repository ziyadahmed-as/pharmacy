<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Line item in a sale, reduced from stock by batch.
 *
 * @property int $id
 * @property int $sale_id
 * @property int $product_id
 * @property int|null $unit_id
 * @property int $batch_id
 * @property int $quantity
 * @property int $unit_price
 * @property int $subtotal
 * @property bool $is_fefo_override
 * @property string|null $override_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SaleLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'product_id',
        'unit_id',
        'batch_id',
        'quantity',
        'unit_price',
        'subtotal',
        'is_fefo_override',
        'override_reason',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'subtotal' => 'integer',
            'is_fefo_override' => 'boolean',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'unit_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function returnLines(): HasMany
    {
        return $this->hasMany(SaleReturnLine::class);
    }

    public function returnedQuantity(): int
    {
        return (int) $this->returnLines()
            ->whereHas('saleReturn', fn ($q) => $q->whereIn('status', [SaleReturn::STATUS_COMPLETED, SaleReturn::STATUS_PENDING_APPROVAL]))
            ->sum('quantity');
    }

    public function remainingReturnableQuantity(): int
    {
        return max(0, $this->quantity - $this->returnedQuantity());
    }

    public function unitPriceEtb(): string
    {
        return number_format($this->unit_price / 100, 2);
    }

    public function subtotalEtb(): string
    {
        return number_format($this->subtotal / 100, 2);
    }
}
