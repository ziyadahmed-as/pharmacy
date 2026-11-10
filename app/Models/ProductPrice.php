<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

/**
 * Append-only selling prices. Never update or delete.
 *
 * @uc UC-02 @fr FR-1.5 @br BR-20 @ac AC-16, AC-17
 */
class ProductPrice extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'unit_id',
        'price',
        'effective_from',
        'reason',
        'set_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'effective_from' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public static function booted(): void
    {
        static::updating(function () {
            throw new RuntimeException('ProductPrice is append-only and cannot be updated.');
        });

        static::deleting(function () {
            throw new RuntimeException('ProductPrice is append-only and cannot be deleted.');
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'unit_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by');
    }
}
