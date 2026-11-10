<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->cascadeOnDelete();
            $table->enum('type', ['low_stock', 'critical_stock', 'near_expiry', 'expired']);
            $table->enum('severity', ['warning', 'critical'])->default('warning');
            $table->integer('threshold_days')->nullable(); // 90, 60, 30 for near expiry
            $table->integer('current_quantity')->default(0);
            $table->integer('reorder_level')->nullable();
            $table->integer('min_level')->nullable();
            $table->text('message');
            $table->enum('status', ['active', 'seen', 'resolved'])->default('active');
            $table->timestamp('seen_at')->nullable();
            $table->foreignId('seen_by')->nullable()->constrained('users');
            $table->string('dedupe_key')->unique();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index('product_id');
            $table->index('batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_alerts');
    }
};
