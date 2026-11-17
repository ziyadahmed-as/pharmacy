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

        // After any sellable quantity change, check if the parent batch should
        // transition to DEPLETED (or back to SELLABLE on a restock / void).
        static::saved(function (BatchStock $batchStock) {
            if ($batchStock->status !== 'sellable') {
                return;  // quarantine rows do not drive the DEPLETED transition
            }

            $batch = $batchStock->batch;
            if (! $batch) {
                return;
            }

            $totalSellable = (int) BatchStock::where('batch_id', $batch->id)
                ->where('status', 'sellable')
                ->sum('quantity');

            if ($totalSellable === 0 && $batch->status === Batch::STATUS_SELLABLE) {
                Batch::withoutEvents(fn () => $batch->updateQuietly(['status' => Batch::STATUS_DEPLETED]));
            } elseif ($totalSellable > 0 && $batch->status === Batch::STATUS_DEPLETED) {
                // Restock / void brought units back — reopen the batch.
                Batch::withoutEvents(fn () => $batch->updateQuietly(['status' => Batch::STATUS_SELLABLE]));
            }
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
