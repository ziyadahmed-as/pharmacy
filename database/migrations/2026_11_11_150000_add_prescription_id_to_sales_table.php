<?php

use App\Models\Prescription;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add prescription_id to sales so a dispensing sale can reference the prescription.
 *
 * @uc UC-21
 *
 * @fr FR-4.8, FR-4.9
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('prescription_id')
                ->nullable()
                ->after('user_id')
                ->constrained('prescriptions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeignIdFor(Prescription::class);
        });
    }
};
