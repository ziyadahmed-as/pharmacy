<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Create controlled_drug_register table with append-only database triggers.
 *
 * @uc UC-22
 *
 * @fr FR-4.4, FR-4.3, FR-4.9
 *
 * @br BR-03, BR-11, BR-12
 *
 * @ac AC-13, AC-34
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('controlled_drug_register', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('batch_id')->constrained('batches')->restrictOnDelete();
            $table->string('entry_type'); // receipt, dispense, adjustment, disposal
            $table->unsignedInteger('quantity');
            $table->integer('running_balance');
            $table->string('patient_name')->nullable();
            $table->string('prescriber_name')->nullable();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('second_person_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->foreignId('stock_movement_id')->nullable()->constrained('stock_movements')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        $this->createAppendOnlyTriggers();
    }

    public function down(): void
    {
        $this->dropAppendOnlyTriggers();
        Schema::dropIfExists('controlled_drug_register');
    }

    private function createAppendOnlyTriggers(): void
    {
        $driver = DB::connection()->getDriverName();
        $msg = 'controlled_drug_register is append-only: updates and deletes are forbidden';

        if ($driver === 'sqlite') {
            DB::unprepared("CREATE TRIGGER controlled_drug_register_no_update BEFORE UPDATE ON controlled_drug_register BEGIN SELECT RAISE(ABORT, '{$msg}'); END;");
            DB::unprepared("CREATE TRIGGER controlled_drug_register_no_delete BEFORE DELETE ON controlled_drug_register BEGIN SELECT RAISE(ABORT, '{$msg}'); END;");
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::unprepared("CREATE TRIGGER controlled_drug_register_no_update BEFORE UPDATE ON controlled_drug_register FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$msg}'");
            DB::unprepared("CREATE TRIGGER controlled_drug_register_no_delete BEFORE DELETE ON controlled_drug_register FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$msg}'");
        }
    }

    private function dropAppendOnlyTriggers(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS controlled_drug_register_no_update');
        DB::unprepared('DROP TRIGGER IF EXISTS controlled_drug_register_no_delete');
    }
};
