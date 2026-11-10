<?php

namespace App\Console\Commands;

use App\Contracts\ExpiredBatchQuarantiner;
use App\Models\Batch;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Quarantines all expired batches with sellable stock (UC-08 Step 3).
 * Run daily and on batch expiry date.
 *
 * @uc UC-08 @fr FR-2.15 @br BR-01, BR-10 @ac AC-07
 */
class QuarantineExpiredBatches extends Command
{
    protected $signature = 'batches:quarantine-expired';

    protected $description = 'Automatically quarantine batches that have reached or passed their expiry date';

    public function handle(ExpiredBatchQuarantiner $quarantiner): int
    {
        $today = Carbon::today()->format('Y-m-d');
        $systemUser = User::systemUser();

        $batches = Batch::query()
            ->where('expiry_date', '<=', $today)
            ->whereHas('stocks', function ($q) {
                $q->where('status', 'sellable')->where('quantity', '>', 0);
            })
            ->with(['product', 'stocks' => fn ($q) => $q->where('status', 'sellable')])
            ->get();

        if ($batches->isEmpty()) {
            $this->info('No expired batches with sellable stock found.');

            return Command::SUCCESS;
        }

        $count = 0;
        foreach ($batches as $batch) {
            $sellableQty = (int) $batch->stocks->sum('quantity');
            $quarantiner->quarantine($batch, $systemUser, 'expired');
            $count++;

            $this->info("Quarantined batch {$batch->batch_no} of {$batch->product->brand} ({$sellableQty} units, expired on {$batch->expiry_date->format('Y-m-d')}).");
        }

        $this->info("Successfully quarantined {$count} expired batch(es).");

        return Command::SUCCESS;
    }
}
