<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds unit_cost to sale_lines so OW/PM profit reports can calculate gross
 * profit per line without joining to batches (which keeps the original cost
 * but this denormalisation is safer and faster for reporting).
 *
 * Nullable so existing sale_lines rows remain valid.
 *
 * @br BR-06 (OW/PM only)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_lines', function (Blueprint $table) {
            // Buying price per base unit at the moment of sale (santim).
            // NULL for lines created before this migration or when batch had no cost.
            $table->unsignedInteger('unit_cost')->nullable()->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('sale_lines', function (Blueprint $table) {
            $table->dropColumn('unit_cost');
        });
    }
};
