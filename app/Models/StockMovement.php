<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class StockMovement extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'batch_id',
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
            'quantity' => 'integer',
            'balance_after' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public static function booted(): void
    {
        static::updating(function () {
            throw new RuntimeException('StockMovement ledger is append-only and cannot be updated.');
        });

        static::deleting(function () {
            throw new RuntimeException('StockMovement ledger is append-only and cannot be deleted.');
        });
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
