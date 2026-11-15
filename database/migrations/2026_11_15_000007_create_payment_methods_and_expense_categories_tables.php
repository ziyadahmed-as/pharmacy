<?php

use App\Support\AppendOnly;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. payment_methods (code, name, is_cash, is_active, requires_reference)
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->boolean('is_cash')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('requires_reference')->default(false);
            $table->timestamps();
        });

        // Seed payment methods: cash, mobile money, bank transfer, credit (plus telebirr, cbe_birr, card)
        $initialPaymentMethods = [
            ['code' => 'cash', 'name' => 'Cash', 'is_cash' => true, 'is_active' => true, 'requires_reference' => false],
            ['code' => 'mobile_money', 'name' => 'Mobile Money', 'is_cash' => false, 'is_active' => true, 'requires_reference' => true],
            ['code' => 'telebirr', 'name' => 'Telebirr', 'is_cash' => false, 'is_active' => true, 'requires_reference' => true],
            ['code' => 'cbe_birr', 'name' => 'CBE Birr', 'is_cash' => false, 'is_active' => true, 'requires_reference' => true],
            ['code' => 'card', 'name' => 'Card', 'is_cash' => false, 'is_active' => true, 'requires_reference' => true],
            ['code' => 'bank_transfer', 'name' => 'Bank Transfer', 'is_cash' => false, 'is_active' => true, 'requires_reference' => true],
            ['code' => 'credit', 'name' => 'Credit Account', 'is_cash' => false, 'is_active' => true, 'requires_reference' => false],
        ];

        foreach ($initialPaymentMethods as $pm) {
            DB::table('payment_methods')->insertOrIgnore(array_merge($pm, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        // Add payment_method_id foreign key to payments table
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('payment_method_id')->nullable()->after('sale_id')->constrained('payment_methods')->restrictOnDelete();
        });

        // Migrate existing payments.method string to payments.payment_method_id
        $methodsMap = DB::table('payment_methods')->pluck('id', 'code');
        foreach ($methodsMap as $code => $id) {
            DB::table('payments')->where('method', $code)->update(['payment_method_id' => $id]);
        }

        // Default any remaining unmapped payments to cash
        $cashId = $methodsMap['cash'] ?? null;
        if ($cashId) {
            DB::table('payments')->whereNull('payment_method_id')->update(['payment_method_id' => $cashId]);
        }

        // Add payment_method_id to sale_returns table
        Schema::table('sale_returns', function (Blueprint $table) {
            $table->foreignId('payment_method_id')->nullable()->after('total_refund')->constrained('payment_methods')->restrictOnDelete();
        });

        foreach ($methodsMap as $code => $id) {
            DB::table('sale_returns')->where('payment_method', $code)->update(['payment_method_id' => $id]);
        }
        if ($cashId) {
            DB::table('sale_returns')->whereNull('payment_method_id')->update(['payment_method_id' => $cashId]);
        }

        // 2. expense_categories (name, is_active)
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed common expense categories
        $initialExpenseCategories = [
            ['code' => 'rent', 'name' => 'Rent', 'is_active' => true],
            ['code' => 'salaries', 'name' => 'Salaries & Wages', 'is_active' => true],
            ['code' => 'utilities', 'name' => 'Utilities (Water, Electricity, Internet)', 'is_active' => true],
            ['code' => 'transport', 'name' => 'Transport & Logistics', 'is_active' => true],
            ['code' => 'maintenance', 'name' => 'Repairs & Maintenance', 'is_active' => true],
            ['code' => 'supplies', 'name' => 'Store & Office Supplies', 'is_active' => true],
            ['code' => 'other', 'name' => 'Other Operational Expenses', 'is_active' => true],
        ];

        foreach ($initialExpenseCategories as $ec) {
            DB::table('expense_categories')->insertOrIgnore(array_merge($ec, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        // Add expense_category_id foreign key to expenses table
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('expense_category_id')->nullable()->after('id')->constrained('expense_categories')->restrictOnDelete();
        });

        // Migrate existing expenses.category string to expenses.expense_category_id
        $expCatMap = DB::table('expense_categories')->pluck('id', 'code');
        foreach ($expCatMap as $code => $id) {
            DB::table('expenses')->where('category', $code)->update(['expense_category_id' => $id]);
        }

        $otherId = $expCatMap['other'] ?? null;
        if ($otherId) {
            DB::table('expenses')->whereNull('expense_category_id')->update(['expense_category_id' => $otherId]);
        }

        // Re-apply triggers and check constraints for payments that were dropped by SQLite table recreation
        AppendOnly::create('payments', blockUpdate: true, blockDelete: true);
        AppendOnly::addCheckConstraint('payments', 'chk_payments_amount', 'amount > 0', 'NEW.amount > 0');
        AppendOnly::addCheckConstraint('payments', 'chk_payments_method', "method IN ('cash', 'mobile_money', 'card', 'bank_transfer', 'telebirr', 'cbe_birr', 'credit')", "NEW.method IN ('cash', 'mobile_money', 'card', 'bank_transfer', 'telebirr', 'cbe_birr', 'credit')");

        // Re-apply expenses no delete trigger for SQLite
        $driver = DB::connection()->getDriverName();
        $msg = 'Expenses cannot be deleted; corrections must be made via reversal entries.';
        if ($driver === 'sqlite') {
            DB::unprepared("CREATE TRIGGER IF NOT EXISTS expenses_no_delete BEFORE DELETE ON expenses BEGIN SELECT RAISE(ABORT, '{$msg}'); END;");
        }
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['expense_category_id']);
            $table->dropColumn('expense_category_id');
        });

        Schema::dropIfExists('expense_categories');

        Schema::table('sale_returns', function (Blueprint $table) {
            $table->dropForeign(['payment_method_id']);
            $table->dropColumn('payment_method_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['payment_method_id']);
            $table->dropColumn('payment_method_id');
        });

        Schema::dropIfExists('payment_methods');
    }
};
