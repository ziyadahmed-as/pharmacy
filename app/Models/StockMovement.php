<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RuntimeException;

/**
 * Append-only stock ledger row (FR-2.10, BR-04, BR-08).
 *
 * Every change to BatchStock.quantity must produce exactly one ledger row in
 * the same database transaction.  Eloquent and a database trigger both refuse
 * updates and deletes.
 *
 * Movement types mirror the BRD spec:
 *   RECEIPT, SALE, SALE_VOID, REFUND_RESTOCK, QUARANTINE,
 *   ADJUSTMENT, DISPOSAL, RETURN_TO_SUPPLIER
 *
 * Legacy type strings stored before this change are kept valid by the CHECK
 * constraint listing both old snake_case and new UPPER values.
 *
 * @property int    $id
 * @property int    $batch_id
 * @property int|null $product_id    Denormalised from batch for fast ledger queries
 * @property int|null $location_id
 * @property string $type
 * @property int    $quantity        Signed: positive = in, negative = out
 * @property int    $balance_after
 * @property string $stock_status   'sellable' | 'quarantine'
 * @property string $source_type
 * @property int    $source_id
 * @property int    $user_id
 * @property string|null $description
 * @property \Carbon\Carbon $created_at
 */
class StockMovement extends Model
{
    use HasFactory;

    public $timestamps = false;

    // -------------------------------------------------------------------------
    // Movement type constants (FR-2.10)
    // -------------------------------------------------------------------------

    public const TYPE_RECEIPT          = 'receipt';
    public const TYPE_SALE             = 'sale';
    public const TYPE_SALE_VOID        = 'sale_void';
    public const TYPE_REFUND_RESTOCK   = 'return_from_customer';   // mapped to BRD "REFUND_RESTOCK"
    public const TYPE_QUARANTINE       = 'quarantine';
    public const TYPE_ADJUSTMENT       = 'adjustment';
    public const TYPE_DISPOSAL         = 'disposal';
    public const TYPE_RETURN_SUPPLIER  = 'supplier_return';        // mapped to BRD "RETURN_TO_SUPPLIER"

    protected $fillable = [
        'batch_id',
        'product_id',
        'location_id',
        'type',
        'quantity',
        'balance_after',
        'stock_status',
        'source_type',
        'source_id',
        'user_id',
        'description',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity'     => 'integer',
            'balance_after'=> 'integer',
            'created_at'   => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Immutability — Eloquent layer (database trigger is a second layer)
    // -------------------------------------------------------------------------

    public static function booted(): void
    {
        static::updating(function () {
            throw new RuntimeException('StockMovement ledger is append-only and cannot be updated.');
        });

        static::deleting(function () {
            throw new RuntimeException('StockMovement ledger is append-only and cannot be deleted.');
        });
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class, 'location_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
