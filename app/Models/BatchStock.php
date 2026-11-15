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
