<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BatchStock extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'location_id',
        'status',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (BatchStock $batchStock) {
            if (empty($batchStock->location_id)) {
                $isControlled = $batchStock->batch?->product?->classification === 'controlled';
                if ($isControlled) {
                    $batchStock->location_id = StorageLocation::getDefaultControlledCabinet()?->id;
                } elseif ($batchStock->status === 'quarantine') {
                    $batchStock->location_id = StorageLocation::getDefaultQuarantine()?->id;
                } else {
                    $batchStock->location_id = StorageLocation::getDefaultDispensary()?->id;
                }
            }
        });

        // Keep batches.quantity_on_hand / quarantine_qty in sync whenever
        // batch_stocks rows are written outside StockService (e.g. in tests,
        // legacy code, or seeding).  StockService suppresses this observer via
        // Batch::withoutEvents / BatchStock::withoutEvents to avoid double-updates.
        static::saved(function (BatchStock $batchStock) {
            $batch = $batchStock->batch;
            if (! $batch) {
                return;
            }

            $sellable   = (int) BatchStock::where('batch_id', $batch->id)
                ->where('status', 'sellable')->sum('quantity');
            $quarantine = (int) BatchStock::where('batch_id', $batch->id)
                ->where('status', 'quarantine')->sum('quantity');

            $newStatus = $batch->status;
            if ($sellable === 0 && $batch->status === Batch::STATUS_SELLABLE) {
                $newStatus = Batch::STATUS_DEPLETED;
            } elseif ($sellable > 0 && $batch->status === Batch::STATUS_DEPLETED) {
                $newStatus = Batch::STATUS_SELLABLE;
            }

            Batch::withoutEvents(fn () => $batch->updateQuietly([
                'quantity_on_hand' => $sellable,
                'quarantine_qty'   => $quarantine,
                'status'           => $newStatus,
            ]));
        });
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class, 'location_id');
    }
}
