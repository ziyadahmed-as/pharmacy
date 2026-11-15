<?php

use App\Support\AppendOnly;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create storage_locations table
        Schema::create('storage_locations', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type'); // shelf, fridge, controlled-cabinet, quarantine, dispensary
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        AppendOnly::addCheckConstraint(
            'storage_locations',
            'chk_storage_locations_type',
            "type IN ('shelf', 'fridge', 'controlled-cabinet', 'quarantine', 'dispensary')",
            "NEW.type IN ('shelf', 'fridge', 'controlled-cabinet', 'quarantine', 'dispensary')"
        );

        // Ensure default locations exist for standard operations
        $defaultLocations = [
            'DISP-MAIN' => ['name' => 'Main Dispensary Shelf', 'type' => 'dispensary'],
            'SHELF-GEN' => ['name' => 'General Shelf', 'type' => 'shelf'],
            'FRIDGE-MAIN' => ['name' => 'Main Refrigerator', 'type' => 'fridge'],
            'SAFE-MAIN' => ['name' => 'Controlled Drug Cabinet', 'type' => 'controlled-cabinet'],
            'QUAR-MAIN' => ['name' => 'Quarantine Area', 'type' => 'quarantine'],
        ];

        foreach ($defaultLocations as $code => $data) {
            DB::table('storage_locations')->insertOrIgnore([
                'code' => $code,
                'name' => $data['name'],
                'type' => $data['type'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 2. Add location_id to batch_stocks (nullable first)
        Schema::table('batch_stocks', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->after('batch_id')->constrained('storage_locations')->restrictOnDelete();
        });

        // 3. Migrate existing text values from batch_stocks.location to storage_locations rows
        $existingLocations = DB::table('batch_stocks')
            ->whereNotNull('location')
            ->where('location', '!=', '')
            ->distinct()
            ->pluck('location');

        foreach ($existingLocations as $locText) {
            $slugCode = strtoupper(Str::slug(substr($locText, 0, 20)));
            if (empty($slugCode)) {
                $slugCode = 'LOC-'.strtoupper(Str::random(6));
            }
            // Ensure unique code
            $baseCode = $slugCode;
            $counter = 1;
            while (DB::table('storage_locations')->where('code', $slugCode)->exists()) {
                $slugCode = substr($baseCode, 0, 15).'-'.$counter;
                $counter++;
            }

            $locId = DB::table('storage_locations')->insertGetId([
                'code' => $slugCode,
                'name' => $locText,
                'type' => 'shelf',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('batch_stocks')
                ->where('location', $locText)
                ->update(['location_id' => $locId]);
        }

        // Assign default location for any batch_stocks with NULL location
        $defaultDispId = DB::table('storage_locations')->where('code', 'DISP-MAIN')->value('id');
        $defaultQuarId = DB::table('storage_locations')->where('code', 'QUAR-MAIN')->value('id');

        DB::table('batch_stocks')
            ->whereNull('location_id')
            ->where('status', 'quarantine')
            ->update(['location_id' => $defaultQuarId]);

        DB::table('batch_stocks')
            ->whereNull('location_id')
            ->update(['location_id' => $defaultDispId]);

        // Drop the legacy text column 'location' and its unique index from batch_stocks
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS batch_stocks_batch_id_status_location_unique');
        } else {
            try {
                Schema::table('batch_stocks', function (Blueprint $table) {
                    $table->dropUnique('batch_stocks_batch_id_status_location_unique');
                });
            } catch (Throwable) {
                // Ignore if index name differs
            }
        }

        Schema::table('batch_stocks', function (Blueprint $table) {
            $table->dropColumn('location');
            $table->unique(['batch_id', 'status', 'location_id']);
        });

        // 4. Add location_id to stock_movements
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->after('batch_id')->constrained('storage_locations')->restrictOnDelete();
        });

        // Update existing stock_movements to reference default location
        DB::table('stock_movements')
            ->whereNull('location_id')
            ->update(['location_id' => $defaultDispId]);

        // 5. Update stock_movements type check constraint to include 'transfer'
        AppendOnly::dropCheckConstraint('stock_movements', 'chk_stock_movements_type');
        AppendOnly::addCheckConstraint(
            'stock_movements',
            'chk_stock_movements_type',
            "type IN ('receipt', 'sale', 'sale_void', 'return', 'quarantine', 'adjustment', 'disposal', 'supplier_return', 'return_from_customer', 'return_quarantine', 'reversal', 'transfer')",
            "NEW.type IN ('receipt', 'sale', 'sale_void', 'return', 'quarantine', 'adjustment', 'disposal', 'supplier_return', 'return_from_customer', 'return_quarantine', 'reversal', 'transfer')"
        );
        AppendOnly::addCheckConstraint('stock_movements', 'chk_stock_movements_stock_status', "stock_status IN ('sellable', 'quarantine')", "NEW.stock_status IN ('sellable', 'quarantine')");

        // Re-apply batch_stocks check constraints and stock_movements immutability (which SQLite rebuild drops)
        AppendOnly::addCheckConstraint('batch_stocks', 'chk_batch_stocks_quantity', 'quantity >= 0', 'NEW.quantity >= 0');
        AppendOnly::addCheckConstraint('batch_stocks', 'chk_batch_stocks_status', "status IN ('sellable', 'quarantine')", "NEW.status IN ('sellable', 'quarantine')");
        AppendOnly::create('stock_movements', blockUpdate: true, blockDelete: true);

        // 6. Add location_id to stock_receipt_lines
        Schema::table('stock_receipt_lines', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->after('product_id')->constrained('storage_locations')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_receipt_lines', function (Blueprint $table) {
            $table->dropForeign(['location_id']);
            $table->dropColumn('location_id');
        });

        AppendOnly::dropCheckConstraint('stock_movements', 'chk_stock_movements_type');
        AppendOnly::addCheckConstraint(
            'stock_movements',
            'chk_stock_movements_type',
            "type IN ('receipt', 'sale', 'sale_void', 'return', 'quarantine', 'adjustment', 'disposal', 'supplier_return', 'return_from_customer', 'return_quarantine', 'reversal')",
            "NEW.type IN ('receipt', 'sale', 'sale_void', 'return', 'quarantine', 'adjustment', 'disposal', 'supplier_return', 'return_from_customer', 'return_quarantine', 'reversal')"
        );

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['location_id']);
            $table->dropColumn('location_id');
        });

        Schema::table('batch_stocks', function (Blueprint $table) {
            $table->string('location')->nullable()->after('batch_id');
            $table->dropForeign(['location_id']);
            $table->dropColumn('location_id');
        });

        AppendOnly::dropCheckConstraint('storage_locations', 'chk_storage_locations_type');
        Schema::dropIfExists('storage_locations');
    }
};
