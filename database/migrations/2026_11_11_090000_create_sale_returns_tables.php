<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->string('refund_no', 60)->unique();
            $table->foreignId('sale_id')->constrained('sales')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('shift_id')->constrained('shifts')->restrictOnDelete();
            $table->string('status', 30)->default('completed'); // 'completed', 'pending_approval', 'rejected'
            $table->unsignedInteger('total_refund'); // in santim
            $table->string('payment_method', 30)->default('cash');
            $table->string('reason', 255)->nullable();
            $table->boolean('requires_approval')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['sale_id', 'status']);
            $table->index(['shift_id', 'status']);
        });

        Schema::create('sale_return_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_return_id')->constrained('sale_returns')->cascadeOnDelete();
            $table->foreignId('sale_line_id')->constrained('sale_lines')->restrictOnDelete();
            $table->foreignId('batch_id')->constrained('batches')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('unit_price'); // in santim
            $table->unsignedInteger('subtotal'); // in santim
            $table->string('assessment', 30); // 'resaleable', 'not_resaleable'
            $table->string('reason', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_return_lines');
        Schema::dropIfExists('sale_returns');
    }
};
