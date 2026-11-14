<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single batch line within a stocktake count sheet (UC-07).
 *
 * @property int $id
 * @property int $stocktake_id
 * @property int $batch_id
 * @property int $system_qty Snapshot at stocktake creation
 * @property int|null $counted_qty
 * @property int|null $variance counted_qty - system_qty
 * @property int|null $recount_qty
 * @property int|null $counted_by
 * @property int|null $second_person_id
 * @property Carbon|null $counted_at
 */
class StocktakeLine extends Model
{
    protected $fillable = [
        'stocktake_id',
        'batch_id',
        'system_qty',
        'counted_qty',
        'variance',
        'recount_qty',
        'counted_by',
        'second_person_id',
        'counted_at',
    ];

    protected function casts(): array
    {
        return [
            'counted_at' => 'datetime',
            'system_qty' => 'integer',
            'counted_qty' => 'integer',
            'variance' => 'integer',
            'recount_qty' => 'integer',
        ];
    }

    // ── Relationships ────────────────────────────────────────────────────────

    public function stocktake(): BelongsTo
    {
        return $this->belongsTo(Stocktake::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function countedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by');
    }

    public function secondPerson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'second_person_id');
    }

    // ── Computed ─────────────────────────────────────────────────────────────

    public function isCounted(): bool
    {
        return $this->counted_qty !== null;
    }

    public function hasVariance(): bool
    {
        return $this->variance !== null && $this->variance !== 0;
    }

    public function effectiveVariance(): int
    {
        // If a recount was performed, use the recount quantity as the basis
        if ($this->recount_qty !== null) {
            return $this->recount_qty - $this->system_qty;
        }

        return $this->variance ?? 0;
    }

    /**
     * Whether a recount is required for this line based on the threshold setting.
     * Uses the same policy as UC-06: threshold in ETB; null = always recount.
     */
    public function requiresRecount(): bool
    {
        $variance = abs($this->variance ?? 0);
        if ($variance === 0) {
            return false;
        }

        $thresholdEtb = setting('inventory.recount_threshold');
        if ($thresholdEtb === null || $thresholdEtb === '') {
            return true;
        }

        $thresholdSantim = (int) round(((float) $thresholdEtb) * 100);
        $varianceValueSantim = $variance * ($this->batch->unit_cost ?? 0);

        return $varianceValueSantim > $thresholdSantim;
    }
}
