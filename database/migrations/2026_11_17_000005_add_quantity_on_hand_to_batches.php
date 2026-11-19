<?php

use App\Support\AppendOnly;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Single-source-of-truth migration.
 *
 * Adds two denormalised quantity columns to `batches`:
 *   quantity_on_hand  — sum of batch_stocks.quantity WHERE status = 'sellable'
 *   quarantine_qty    — sum of batch_stocks.quantity WHERE status = 'quarantine'
 *
 * Both are kept in sync exclusively by StockService::applyMovement().
 * Database CHECK constraints enforce non-negative values.
 *
 * Also creates one opening RECEIPT ledger row for every batch that already
 * has stock but was created before this migration (back-fill).
 *
 * @br BR-04, BR-08
 */
return new class extends Migration
{
    public function up(): void
    {
        // ------------------------------------------------------------------ //
        // 1. Add columns (nullable first so populate can run before NOT NULL) //
        // ------------------------------------------------------------------ //
        Schema::table('batches', function (Blueprint $table) {
            $table->unsignedInteger('quantity_on_hand')->default(0)->after('status');
            $table->unsignedInteger('quarantine_qty')->default(0)->after('quantity_on_hand');
        });

        // ------------------------------------------------------------------ //
        // 2. Populate from existing batch_stocks rows                         //
        // ------------------------------------------------------------------ //
        DB::statement("
            UPDATE batches
            SET quantity_on_hand = COALESCE((
                SELECT SUM(batch_stocks.quantity)
                FROM batch_stocks
                WHERE batch_stocks.batch_id = batches.id
                  AND batch_stocks.status = 'sellable'
            ), 0),
            quarantine_qty = COALESCE((
                SELECT SUM(batch_stocks.quantity)
                FROM batch_stocks
                WHERE batch_stocks.batch_id = batches.id
                  AND batch_stocks.status = 'quarantine'
            ), 0)
        ");

        // ------------------------------------------------------------------ //
        // 3. CHECK constraints (non-negative)                                 //
        // ------------------------------------------------------------------ //
        AppendOnly::addCheckConstraint(
            'batches',
            'chk_batches_quantity_on_hand',
            'quantity_on_hand >= 0',
            'NEW.quantity_on_hand >= 0'
        );
        AppendOnly::addCheckConstraint(
            'batches',
            'chk_batches_quarantine_qty',
            'quarantine_qty >= 0',
            'NEW.quarantine_qty >= 0'
        );

        // ------------------------------------------------------------------ //
        // 4. Performance index for fast product-level stock reads             //
        // ------------------------------------------------------------------ //
        Schema::table('batches', function (Blueprint $table) {
            $table->index(['product_id', 'quantity_on_hand', 'expiry_date'], 'idx_batches_product_qty_exp');
        });

        // ------------------------------------------------------------------ //
        // 5. Back-fill opening RECEIPT ledger rows for batches that have      //
        //    quantity_on_hand > 0 but no existing ledger row                  //
        //    (pre-migration data).  Uses system user (id from users table).   //
        // ------------------------------------------------------------------ //
        $systemUserId = DB::table('users')->where('username', 'system')->value('id') ?? 1;
        $now = now()->toDateTimeString();

        $orphanBatches = DB::table('batches')
            ->where('quantity_on_hand', '>', 0)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('stock_movements')
                    ->whereColumn('stock_movements.batch_id', 'batches.id');
            })
            ->get(['id', 'product_id', 'quantity_on_hand', 'quarantine_qty']);

        foreach ($orphanBatches as $batch) {
            if ($batch->quantity_on_hand > 0) {
                DB::table('stock_movements')->insert([
                    'batch_id'      => $batch->id,
                    'product_id'    => $batch->product_id,
                    'type'          => 'receipt',
                    'quantity'      => $batch->quantity_on_hand,
                    'balance_after' => $batch->quantity_on_hand,
                    'stock_status'  => 'sellable',
                    'source_type'   => 'App\Models\Batch',
                    'source_id'     => $batch->id,
                    'user_id'       => $systemUserId,
                    'description'   => 'Opening stock — migrated from batch_stocks',
                    'created_at'    => $now,
                ]);
            }
            if ($batch->quarantine_qty > 0) {
                DB::table('stock_movements')->insert([
                    'batch_id'      => $batch->id,
                    'product_id'    => $batch->product_id,
                    'type'          => 'quarantine',
                    'quantity'      => $batch->quarantine_qty,
                    'balance_after' => $batch->quarantine_qty,
                    'stock_status'  => 'quarantine',
                    'source_type'   => 'App\Models\Batch',
                    'source_id'     => $batch->id,
                    'user_id'       => $systemUserId,
                    'description'   => 'Opening quarantine stock — migrated from batch_stocks',
                    'created_at'    => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->dropIndex('idx_batches_product_qty_exp');
        });
        AppendOnly::dropCheckConstraint('batches', 'chk_batches_quarantine_qty');
        AppendOnly::dropCheckConstraint('batches', 'chk_batches_quantity_on_hand');
        Schema::table('batches', function (Blueprint $table) {
            $table->dropColumn(['quantity_on_hand', 'quarantine_qty']);
        });
    }
};
