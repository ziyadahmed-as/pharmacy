<?php

namespace App\Services;

use App\Actions\Inventory\QuarantineStock;
use App\Contracts\ExpiredBatchQuarantiner;
use App\Models\Batch;
use App\Models\BatchStock;
use App\Models\QuarantineRecord;
use App\Models\User;

/**
 * Service implementing ExpiredBatchQuarantiner contract (UC-05, UC-08).
 *
 * @uc UC-08 @fr FR-2.15 @br BR-01, BR-10 @ac AC-07
 */
class ExpiredBatchQuarantinerService implements ExpiredBatchQuarantiner
{
    public function __construct(private QuarantineStock $quarantineStock) {}

    /**
     * Quarantine an expired batch without changing overall product stock balance negatively.
     */
    public function quarantine(Batch $batch, ?User $actor = null, ?string $reason = null): void
    {
        $user = $actor ?? User::systemUser();
        $quarantineReason = $reason ?? QuarantineRecord::REASON_EXPIRED;

        $sellableQuantity = (int) BatchStock::where('batch_id', $batch->id)
            ->where('status', 'sellable')
            ->sum('quantity');

        if ($sellableQuantity <= 0) {
            return;
        }

        $this->quarantineStock->execute(
            batch: $batch,
            quantity: $sellableQuantity,
            reason: $quarantineReason,
            note: 'Automated quarantine of expired batch',
            actor: $user
        );
    }
}
