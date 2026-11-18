<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes that make the Expiry & Stock widget fast (NFR-1: < 2 s).
 *
 * idx_bstocks_status_qty   — sort-by-qty-remaining; filter by status
 * idx_batches_mfg_product  — sort by manufacturing date (longest since manufacture)
 * idx_batches_created      — sort by recently registered (registered_at)
 *
 * The expiry indexes already exist from migration 2026_11_17_000002.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batch_stocks', function (Blueprint $table) {
            // Covers: WHERE status = 'sellable' ORDER BY quantity ASC/DESC
            $table->index(['status', 'quantity'], 'idx_bstocks_status_qty');
        });

        Schema::table('batches', function (Blueprint $table) {
            // Covers: ORDER BY manufacturing_date ASC (longest since manufacture)
            $table->index(['manufacturing_date', 'product_id'], 'idx_batches_mfg_product');
            // Covers: ORDER BY registered_at DESC (recently registered)
            $table->index(['registered_at'], 'idx_batches_registered_at');
        });
    }

    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->dropIndex('idx_batches_registered_at');
            $table->dropIndex('idx_batches_mfg_product');
        });

        Schema::table('batch_stocks', function (Blueprint $table) {
            $table->dropIndex('idx_bstocks_status_qty');
        });
    }
};
