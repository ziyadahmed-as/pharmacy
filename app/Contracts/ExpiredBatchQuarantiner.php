<?php

namespace App\Contracts;

use App\Models\Batch;
use App\Models\User;

/**
 * Contract for automatically or manually moving expired batches into quarantine.
 * Defined in UC-05 Step 2; implemented in UC-08 (Quarantine stock).
 *
 * @uc UC-05, UC-08
 */
interface ExpiredBatchQuarantiner
{
    /**
     * Quarantine an expired batch without changing its ledger balance negatively.
     */
    public function quarantine(Batch $batch, ?User $actor = null, ?string $reason = null): void;
}

