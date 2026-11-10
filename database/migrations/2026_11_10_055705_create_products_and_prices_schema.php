<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // UC-01 Categories
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // UC-01 Products
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('brand');
            $table->string('generic_name'); // INN
            $table->string('strength')->nullable();
            $table->string('dosage_form')->nullable();
            $table->string('pack_size')->nullable();
            $table->string('unit')->default('piece');
            $table->string('manufacturer')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('barcode')->nullable()->unique();
            $table->string('storage_condition')->nullable();
            $table->enum('classification', ['rx', 'otc', 'controlled', 'non_drug'])->default('otc');
            $table->boolean('loose_allowed')->default(false);
            $table->unsignedInteger('reorder_level')->default(0);
            $table->unsignedInteger('max_level')->default(0);
            $table->unsignedInteger('min_level')->default(0);
            $table->text('preferred_supplier_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Index for search performance and duplicate check
            $table->index(['brand', 'strength', 'dosage_form', 'pack_size'], 'idx_products_duplicate_lookup');
            $table->index(['generic_name', 'brand', 'barcode', 'is_active'], 'idx_products_search');
        });

        // UC-01 Product Units
        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('factor')->default(1); // factor to base unit
            $table->timestamps();

            $table->unique(['product_id', 'name']);
        });

        // UC-02 Product Prices (Append-only history, never update a row)
        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('product_units')->nullOnDelete();
            $table->unsignedInteger('price'); // price in minor units (e.g. santim)
            $table->dateTime('effective_from');
            $table->string('reason');
            $table->foreignId('set_by')->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['product_id', 'unit_id', 'effective_from'], 'idx_prices_resolver');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_prices');
        Schema::dropIfExists('product_units');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
    }
};
