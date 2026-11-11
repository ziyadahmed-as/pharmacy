<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * UC-14: Hold and resume a sale
     * FR-3.6; AC-23.
     */
    public function up(): void
    {
        Schema::create('held_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('shift_id')->constrained('shifts');
            $table->string('reference', 150)->nullable();
            $table->json('cart');
            $table->unsignedBigInteger('total')->default(0); // in santim
            $table->enum('status', ['held', 'resumed', 'discarded'])->default('held');
            $table->timestamp('held_at')->useCurrent();
            $table->timestamp('resumed_at')->nullable();
            $table->timestamp('discarded_at')->nullable();
            $table->string('discard_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['shift_id', 'status']);
            $table->index(['status', 'held_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('held_sales');
    }
};

