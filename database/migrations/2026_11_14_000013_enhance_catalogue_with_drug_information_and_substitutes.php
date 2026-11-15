<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Enhance categories with parent_category_id and is_active
        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('parent_category_id')->nullable()->after('name')->constrained('categories')->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('description');
        });

        // 2. Enhance products with product_code, is_cold_chain, min_shelf_life_days
        Schema::table('products', function (Blueprint $table) {
            $table->string('product_code', 40)->nullable()->unique()->after('id');
            $table->boolean('is_cold_chain')->default(false)->after('storage_condition');
            $table->unsignedInteger('min_shelf_life_days')->nullable()->after('loose_allowed');
        });

        // 3. Create drug_information table (DDD 5.3)
        Schema::create('drug_information', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained('products')->cascadeOnDelete();
            $table->text('indications')->nullable();
            $table->text('dosage_guidance')->nullable();
            $table->text('side_effects')->nullable();
            $table->text('contraindications')->nullable();
            $table->text('interactions')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 4. Create product_substitutes table (DDD 5.3)
        Schema::create('product_substitutes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('substitute_product_id')->constrained('products')->cascadeOnDelete();
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'substitute_product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_substitutes');
        Schema::dropIfExists('drug_information');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['product_code', 'is_cold_chain', 'min_shelf_life_days']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign(['parent_category_id']);
            $table->dropColumn(['parent_category_id', 'is_active']);
        });
    }
};
