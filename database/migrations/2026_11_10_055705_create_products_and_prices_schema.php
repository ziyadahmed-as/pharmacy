<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop any partially created tables if a previous migration attempt failed
        Schema::dropIfExists('product_prices');
        Schema::dropIfExists('product_units');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');

        // UC-01 Categories
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // UC-01 Products
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('brand', 150);
            $table->string('generic_name', 150); // INN
            $table->string('strength', 60)->nullable();
            $table->string('dosage_form', 60)->nullable();
            $table->string('pack_size', 60)->nullable();
            $table->string('unit', 30)->default('piece');
            $table->string('manufacturer', 150)->nullable();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('barcode', 100)->nullable()->unique();
            $table->string('storage_condition', 150)->nullable();
            $table->enum('classification', ['rx', 'otc', 'controlled', 'non_drug'])->default('otc');
            $table->boolean('loose_allowed')->default(false);
            $table->unsignedInteger('reorder_level')->default(0);
            $table->unsignedInteger('max_level')->default(0);
            $table->unsignedInteger('min_level')->default(0);
            $table->text('preferred_supplier_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Index for search performance and duplicate check (specified key lengths safe for MySQL utf8mb4)
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
