<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 
 * @property int         $id
 * @property int         $product_id
 * @property string      $batch_no
 * @property \Carbon\Carbon|null $manufacturing_date
 * @property \Carbon\Carbon      $expiry_date
 * @property int|null    $unit_cost          Buying price per base unit in santim (OW/PM only)
 * @property string|null $supplier_name
 * @property string|null $delivery_note_no
 * @property int|null    $registered_by
 * @property \Carbon\Carbon|null $registered_at
 * @property string      $status             SELLABLE | QUARANTINED | RECALLED | DEPLETED
 *
 * @fr FR-2.1
 */
class Batch extends Model
{
    use HasFactory;

    /** Valid status values — mirrors the CHECK constraint in the migration. */
    public const STATUS_SELLABLE    = 'SELLABLE';
    public const STATUS_QUARANTINED = 'QUARANTINED';
    public const STATUS_RECALLED    = 'RECALLED';
    public const STATUS_DEPLETED    = 'DEPLETED';

    public const STATUSES = [
        self::STATUS_SELLABLE,
        self::STATUS_QUARANTINED,
        self::STATUS_RECALLED,
        self::STATUS_DEPLETED,
    ];

    protected $fillable = [
        'product_id',
        'batch_no',
        'manufacturing_date',
        'expiry_date',
        'unit_cost',
        'supplier_name',
        'delivery_note_no',
        'registered_by',
        'registered_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'manufacturing_date' => 'date',
            'expiry_date'        => 'date',
            'unit_cost'          => 'integer',
            'registered_at'      => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(BatchStock::class);
    }

    public function batchStocks(): HasMany
    {
        return $this->hasMany(BatchStock::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function quarantineRecords(): HasMany
    {
        return $this->hasMany(QuarantineRecord::class);
    }

    public function disposals(): HasMany
    {
        return $this->hasMany(Disposal::class);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function sellableStockQuantity(): int
    {
        return (int) $this->batchStocks()->where('status', 'sellable')->sum('quantity');
    }

    public function quarantineStockQuantity(): int
    {
        return (int) $this->batchStocks()->where('status', 'quarantine')->sum('quantity');
    }

    public function isSellable(): bool
    {
        return $this->status === self::STATUS_SELLABLE;
    }

    public function isDepleted(): bool
    {
        return $this->status === self::STATUS_DEPLETED;
    }
}
