<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Services\AuditLogger;
use App\Services\Notifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Verifies that every batch's quantity_on_hand and quarantine_qty equal the
 * sum of their respective stock_movements ledger rows.
 *
 * Run nightly and whenever a reconciliation is needed.
 * Alerts OW/PM on any mismatch.
 *
 * @br BR-04, BR-08
 */
class StockIntegrityCheck extends Command
{
    protected $signature = 'stock:integrity-check
                            {--batch= : Check a single batch by ID}
                            {--fix   : Write a corrective adjustment movement for each mismatch (DRY-RUN by default)}';

    protected $description = 'Verify that every batch quantity_on_hand matches the sum of its stock_movements ledger rows';

    public function handle(): int
    {
        $batchId = $this->option('batch');
        $fix     = (bool) $this->option('fix');

        $this->info('Running stock integrity check…');

        // Build the reconciliation query
        $query = DB::table('batches as b')
            ->leftJoin(
                DB::raw('(SELECT batch_id,
                                SUM(CASE WHEN stock_status = "sellable"   THEN quantity ELSE 0 END) AS ledger_sellable,
                                SUM(CASE WHEN stock_status = "quarantine" THEN quantity ELSE 0 END) AS ledger_quarantine
                         FROM stock_movements
                         GROUP BY batch_id) AS sm'),
                'sm.batch_id', '=', 'b.id'
            )
            ->join('products as p', 'p.id', '=', 'b.product_id')
            ->select([
                'b.id as batch_id',
                'b.batch_no',
                'p.brand',
                'b.quantity_on_hand',
                'b.quarantine_qty',
                DB::raw('COALESCE(sm.ledger_sellable,   0) as ledger_sellable'),
                DB::raw('COALESCE(sm.ledger_quarantine, 0) as ledger_quarantine'),
                DB::raw('b.quantity_on_hand - COALESCE(sm.ledger_sellable,   0) as diff_sellable'),
                DB::raw('b.quarantine_qty   - COALESCE(sm.ledger_quarantine, 0) as diff_quarantine'),
            ])
            ->having(DB::raw('ABS(diff_sellable)'),   '>', 0)
            ->orHaving(DB::raw('ABS(diff_quarantine)'), '>', 0);

        if ($batchId) {
            $query->where('b.id', (int) $batchId);
        }

        $mismatches = $query->get();

        if ($mismatches->isEmpty()) {
            $this->info('✓  All batches pass integrity check — no discrepancies found.');
            return self::SUCCESS;
        }

        $this->error("⚠  Found {$mismatches->count()} batch(es) with quantity_on_hand ≠ ledger sum:");

        $rows = [];
        foreach ($mismatches as $m) {
            $rows[] = [
                $m->batch_id,
                $m->brand,
                $m->batch_no,
                $m->quantity_on_hand,
                $m->ledger_sellable,
                (int) $m->diff_sellable,
                $m->quarantine_qty,
                $m->ledger_quarantine,
                (int) $m->diff_quarantine,
            ];
        }

        $this->table(
            ['Batch ID', 'Product', 'Batch No', 'QoH', 'Ledger Sellable', 'Δ Sellable', 'QoH Quar', 'Ledger Quar', 'Δ Quar'],
            $rows
        );

        // Alert OW/PM
        Notifier::toRoles(
            [Role::Owner, Role::PharmacyManager],
            'stock.integrity.mismatch',
            [
                'mismatch_count' => $mismatches->count(),
                'message_key'    => 'catalogue.integrity_mismatch_notification',
                'message_params' => ['count' => $mismatches->count()],
            ],
            'stock-integrity-mismatch-' . now()->toDateString()
        );

        AuditLogger::record(
            'stock.integrity.check_failed',
            null, [], [],
            [
                'mismatch_count' => $mismatches->count(),
                'batches'        => $mismatches->pluck('batch_id')->all(),
            ]
        );

        if ($fix) {
            $this->warn('--fix mode: writing corrective ADJUSTMENT movements…');
            $systemUser = \App\Models\User::systemUser();

            foreach ($mismatches as $m) {
                $batch = \App\Models\Batch::find($m->batch_id);
                if (! $batch) {
                    continue;
                }

                // Fix sellable drift
                if ((int) $m->diff_sellable !== 0) {
                    // Directly patch the SSOT column (corrective — no source document)
                    $batch->updateQuietly(['quantity_on_hand' => max(0, (int) $m->ledger_sellable)]);
                    DB::table('stock_movements')->insert([
                        'batch_id'      => $batch->id,
                        'product_id'    => $batch->product_id,
                        'type'          => 'adjustment',
                        'quantity'      => -(int) $m->diff_sellable,
                        'balance_after' => max(0, (int) $m->ledger_sellable),
                        'stock_status'  => 'sellable',
                        'source_type'   => self::class,
                        'source_id'     => $batch->id,
                        'user_id'       => $systemUser->id,
                        'description'   => 'Corrective adjustment from stock integrity check',
                        'created_at'    => now(),
                    ]);
                }

                // Fix quarantine drift
                if ((int) $m->diff_quarantine !== 0) {
                    $batch->updateQuietly(['quarantine_qty' => max(0, (int) $m->ledger_quarantine)]);
                    DB::table('stock_movements')->insert([
                        'batch_id'      => $batch->id,
                        'product_id'    => $batch->product_id,
                        'type'          => 'adjustment',
                        'quantity'      => -(int) $m->diff_quarantine,
                        'balance_after' => max(0, (int) $m->ledger_quarantine),
                        'stock_status'  => 'quarantine',
                        'source_type'   => self::class,
                        'source_id'     => $batch->id,
                        'user_id'       => $systemUser->id,
                        'description'   => 'Corrective adjustment from stock integrity check',
                        'created_at'    => now(),
                    ]);
                }

                $this->line("  Fixed batch {$batch->batch_no} (ID {$batch->id})");
            }
        }

        return self::FAILURE;
    }
}
