<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Document sequences for atomic reference numbers (e.g. REC-2026-00001)
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('type')->unique();
            $table->unsignedBigInteger('next_value')->default(1);
            $table->string('prefix')->default('');
            $table->timestamps();
        });

        // STEP 1 Batches
        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('batch_no');
            $table->date('manufacturing_date')->nullable();
            $table->date('expiry_date');
            $table->unsignedInteger('unit_cost')->nullable(); // stored in minor units (santim)
            $table->text('supplier_name')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'batch_no']);
            $table->index(['expiry_date', 'product_id']);
        });

        // Batch Stocks (quantity per batch, location, and status)
        Schema::create('batch_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('batches')->cascadeOnDelete();
            $table->string('location')->nullable();
            $table->enum('status', ['sellable', 'quarantine'])->default('sellable');
            $table->integer('quantity')->default(0); // in base unit
            $table->timestamps();

            $table->unique(['batch_id', 'status', 'location']);
        });

        // Stock Receipts (Header)
        Schema::create('stock_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('supplier_name');
            $table->string('delivery_note_no')->nullable();
            $table->date('receipt_date');
            $table->enum('status', ['draft', 'pending_confirmation', 'posted', 'rejected'])->default('draft');
            $table->string('idempotency_key')->unique();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('confirmed_by')->nullable()->constrained('users');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        // Stock Receipt Lines
        Schema::create('stock_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_receipt_id')->constrained('stock_receipts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->string('batch_no');
            $table->date('manufacturing_date')->nullable();
            $table->date('expiry_date');
            $table->integer('quantity'); // in base units
            $table->unsignedInteger('unit_cost')->nullable(); // minor units
            $table->enum('flag', ['ok', 'short', 'over', 'damaged'])->default('ok');
            $table->timestamps();
        });

        // Stock Movements Ledger (Append-only)
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('batches');
            $table->string('type'); // receipt, sale, adjustment, quarantine, return
            $table->integer('quantity'); // signed quantity (+ receipt, - sale)
            $table->integer('balance_after'); // balance of the batch stock after posting
            $table->string('stock_status')->default('sellable'); // sellable or quarantine
            $table->string('source_type'); // e.g. StockReceipt, Sale
            $table->unsignedBigInteger('source_id');
            $table->foreignId('user_id')->constrained('users');
            $table->string('description')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['batch_id', 'created_at']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_receipt_lines');
        Schema::dropIfExists('stock_receipts');
        Schema::dropIfExists('batch_stocks');
        Schema::dropIfExists('batches');
        Schema::dropIfExists('document_sequences');
    }
};
