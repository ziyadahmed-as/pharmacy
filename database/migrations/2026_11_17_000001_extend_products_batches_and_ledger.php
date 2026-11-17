<?php

use App\Support\AppendOnly;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extends the data model per FR-1.1, FR-1.5, FR-2.1, FR-2.10:
 *
 *  products         — image_path (nullable), selling_price (unsigned int, minor units)
 *  batches          — delivery_note_no, registered_by, registered_at, status enum
 *  stock_movements  — product_id (for direct ledger look-ups without joining batches)
 *
 * All new columns are additive; no existing column is modified.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ------------------------------------------------------------------ //
        // 1. products: image and selling price                                //
        // ------------------------------------------------------------------ //
        Schema::table('products', function (Blueprint $table) {
            // Path stored on the 'private' disk; nullable until an image is uploaded.
            $table->string('image_path', 500)->nullable()->after('is_active');

            // Path stored on the 'public' disk for the 200×200 thumbnail.
            $table->string('thumbnail_path', 500)->nullable()->after('image_path');

            // Canonical selling price in minor units (santim). NULL means "not priced yet".
            // The authoritative price history lives in product_prices; this column is a
            // convenience denormalisation for fast POS look-ups and must always equal
            // the latest active product_prices row.
            $table->unsignedBigInteger('selling_price')->nullable()->after('image_path')
                ->comment('Unit selling price in minor units (santim). Must be > 0 when set.');

            // Also add product_code and min_shelf_life_days if not present (some builds
            // may not have them from the original migration).
            if (! Schema::hasColumn('products', 'product_code')) {
                $table->string('product_code', 60)->nullable()->after('id');
            }
            if (! Schema::hasColumn('products', 'min_shelf_life_days')) {
                $table->unsignedInteger('min_shelf_life_days')->nullable()->after('preferred_supplier_name');
            }
            if (! Schema::hasColumn('products', 'is_cold_chain')) {
                $table->boolean('is_cold_chain')->default(false)->after('storage_condition');
            }
        });

        // selling_price must be > 0 when present
        AppendOnly::addCheckConstraint(
            'products',
            'chk_products_selling_price',
            'selling_price IS NULL OR selling_price > 0',
            'NEW.selling_price IS NULL OR NEW.selling_price > 0'
        );

        // ------------------------------------------------------------------ //
        // 2. batches: extra receipt fields + status                           //
        // ------------------------------------------------------------------ //
        Schema::table('batches', function (Blueprint $table) {
            // Which stock receipt document this batch arrived with.
            $table->string('delivery_note_no', 150)->nullable()->after('supplier_name');

            // The user who created (registered) this batch row.
            $table->foreignId('registered_by')
                ->nullable()
                ->after('delivery_note_no')
                ->constrained('users')
                ->restrictOnDelete();

            // When the batch was first registered.
            $table->timestamp('registered_at')->nullable()->after('registered_by');

            // Operational lifecycle of the batch.
            // SELLABLE  — available for dispensing (default after receipt confirmation)
            // QUARANTINED — isolated; must not be sold
            // RECALLED  — under supplier/authority recall; must not be sold
            // DEPLETED  — quantity_on_hand reached 0 (set automatically)
            $table->enum('status', ['SELLABLE', 'QUARANTINED', 'RECALLED', 'DEPLETED'])
                ->default('SELLABLE')
                ->after('registered_at');
        });

        AppendOnly::addCheckConstraint(
            'batches',
            'chk_batches_status',
            "status IN ('SELLABLE', 'QUARANTINED', 'RECALLED', 'DEPLETED')",
            "NEW.status IN ('SELLABLE', 'QUARANTINED', 'RECALLED', 'DEPLETED')"
        );

        // expiry_date must be strictly after manufacturing_date when both are present.
        // SQLite and MySQL express this differently; AppendOnly handles it.
        AppendOnly::addCheckConstraint(
            'batches',
            'chk_batches_expiry_after_mfg',
            'manufacturing_date IS NULL OR expiry_date > manufacturing_date',
            'NEW.manufacturing_date IS NULL OR NEW.expiry_date > NEW.manufacturing_date'
        );

        // ------------------------------------------------------------------ //
        // 3. stock_movements: add product_id for direct ledger queries        //
        // ------------------------------------------------------------------ //
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('product_id')
                ->nullable()         // nullable so old rows without it remain valid
                ->after('batch_id')
                ->constrained('products')
                ->restrictOnDelete();

            $table->index(['product_id', 'created_at'], 'idx_movements_product_time');
        });
    }

    public function down(): void
    {
        // stock_movements
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex('idx_movements_product_time');
            $table->dropForeign(['product_id']);
            $table->dropColumn('product_id');
        });

        // batches
        AppendOnly::dropCheckConstraint('batches', 'chk_batches_expiry_after_mfg');
        AppendOnly::dropCheckConstraint('batches', 'chk_batches_status');
        Schema::table('batches', function (Blueprint $table) {
            $table->dropForeign(['registered_by']);
            $table->dropColumn(['delivery_note_no', 'registered_by', 'registered_at', 'status']);
        });

        // products
        AppendOnly::dropCheckConstraint('products', 'chk_products_selling_price');
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['image_path', 'thumbnail_path', 'selling_price']);
        });
    }
};
