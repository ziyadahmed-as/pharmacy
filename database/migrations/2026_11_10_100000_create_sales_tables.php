<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * UC-13: Sell OTC and non-drug items
     * FR-3.1..3.5, 3.11, 2.4; BR-01..BR-05, BR-20; AC-01, AC-02, AC-04, AC-20, AC-22, AC-31, AC-36.
     */
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_no', 64)->unique();
            $table->foreignId('shift_id')->constrained('shifts');
            $table->foreignId('user_id')->constrained('users');
            $table->enum('status', ['completed', 'voided'])->default('completed');
            $table->unsignedBigInteger('total'); // in minor units / santim
            $table->string('idempotency_key', 128)->unique();
            $table->unsignedInteger('reprint_count')->default(0);
            $table->timestamp('completed_at')->useCurrent();
            $table->timestamps();

            $table->index(['shift_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('sale_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('unit_id')->nullable()->constrained('product_units')->nullOnDelete();
            $table->foreignId('batch_id')->constrained('batches');
            $table->unsignedInteger('quantity'); // in base units
            $table->unsignedBigInteger('unit_price'); // in minor units / santim
            $table->unsignedBigInteger('subtotal'); // in minor units / santim
            $table->boolean('is_fefo_override')->default(false);
            $table->string('override_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['product_id', 'batch_id']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->string('method', 50); // cash, mobile_money, card, bank_transfer, telebirr, cbe_birr
            $table->unsignedBigInteger('amount'); // in minor units / santim
            $table->string('reference', 128)->nullable(); // transaction reference for electronic methods
            $table->timestamps();

            $table->index(['sale_id', 'method']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('sale_lines');
        Schema::dropIfExists('sales');
    }
};
