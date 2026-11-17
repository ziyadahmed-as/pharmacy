<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance indexes for the nearest-expiry-first sort (NFR-1, NFR-6).
 *
 * The sort key is: MIN(expiry_date) of non-expired, non-recalled, sellable,
 * quantity > 0 batches for a product.  Two indexes make that sub-query fast:
 *
 *  idx_batches_expiry_product  — covers the WHERE + ORDER BY on batches
 *  idx_bstocks_batch_status_qty — covers the EXISTS / JOIN on batch_stocks
 *
 * Both are additive (no existing column touched).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            // Supports:  WHERE expiry_date > NOW() AND product_id = ?  ORDER BY expiry_date
            // Also used by: FefoBatchResolver, QuarantineExpiredBatches, EvaluateStockAlerts
            $table->index(['expiry_date', 'product_id'], 'idx_batches_expiry_product');
        });

        Schema::table('batch_stocks', function (Blueprint $table) {
            // Supports:  WHERE batch_id = ? AND status = 'sellable' AND quantity > 0
            // The status + quantity columns together allow an index-only scan for
            // the aggregation sub-query that calculates total_sellable_qty.
            $table->index(['batch_id', 'status', 'quantity'], 'idx_bstocks_batch_status_qty');
        });
    }

    public function down(): void
    {
        Schema::table('batch_stocks', function (Blueprint $table) {
            $table->dropIndex('idx_bstocks_batch_status_qty');
        });

        Schema::table('batches', function (Blueprint $table) {
            $table->dropIndex('idx_batches_expiry_product');
        });
    }
};
