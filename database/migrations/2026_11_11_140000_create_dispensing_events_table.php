<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create dispensing_events table linking each dispensed prescription line to a
 * batch, sale and the dispensing user.
 *
 * @uc UC-21
 *
 * @fr FR-4.1, FR-4.8, FR-4.9, FR-4.10
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispensing_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_line_id')
                ->constrained('prescription_lines')
                ->cascadeOnDelete();
            $table->foreignId('batch_id')
                ->constrained('batches')
                ->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('sale_id')
                ->nullable()
                ->constrained('sales')
                ->nullOnDelete();
            $table->timestamp('dispensed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispensing_events');
    }
};
