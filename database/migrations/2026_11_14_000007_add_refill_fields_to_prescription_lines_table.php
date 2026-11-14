<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @uc UC-24 @fr FR-4.5, 4.8, 4.10; D-08
     */
    public function up(): void
    {
        Schema::table('prescription_lines', function (Blueprint $table) {
            $table->unsignedInteger('remaining_quantity')->nullable()->after('quantity_dispensed');
            $table->unsignedInteger('refills_allowed')->default(0)->after('remaining_quantity');
            $table->unsignedInteger('refills_used')->default(0)->after('refills_allowed');
            $table->date('next_refill_date')->nullable()->after('refills_used');
        });

        // Initialize remaining_quantity for existing records
        DB::table('prescription_lines')->whereNull('remaining_quantity')->update([
            'remaining_quantity' => DB::raw('CASE WHEN quantity_prescribed > quantity_dispensed THEN quantity_prescribed - quantity_dispensed ELSE 0 END'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prescription_lines', function (Blueprint $table) {
            $table->dropColumn([
                'remaining_quantity',
                'refills_allowed',
                'refills_used',
                'next_refill_date',
            ]);
        });
    }
};
