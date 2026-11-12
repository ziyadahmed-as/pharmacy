<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add search indexes for prescription search queries (patient, prescriber, date range, status, drug).
 *
 * @uc UC-23
 *
 * @fr FR-4.12, FR-7.4
 *
 * @ac AC-26
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->index('prescription_date');
            $table->index('prescriber_name');
            $table->index('prescriber_registration_no');
            $table->index(['status', 'prescription_date']);
        });

        Schema::table('prescription_lines', function (Blueprint $table) {
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropIndex(['prescription_date']);
            $table->dropIndex(['prescriber_name']);
            $table->dropIndex(['prescriber_registration_no']);
            $table->dropIndex(['status', 'prescription_date']);
        });

        Schema::table('prescription_lines', function (Blueprint $table) {
            $table->dropIndex(['product_id']);
        });
    }
};
