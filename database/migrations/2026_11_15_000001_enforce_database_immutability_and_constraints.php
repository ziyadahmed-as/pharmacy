<?php

use App\Support\AppendOnly;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Enforce database-level immutability, check constraints, and RESTRICT on delete.
     */
    public function up(): void
    {
        // 1. Replace cascadeOnDelete and nullOnDelete with restrictOnDelete
        Schema::disableForeignKeyConstraints();

        // Audit tables
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });

        // Sales tables
        Schema::table('sale_lines', function (Blueprint $table) {
            $table->dropForeign(['sale_id']);
            $table->foreign('sale_id')->references('id')->on('sales')->restrictOnDelete();
            $table->dropForeign(['unit_id']);
            $table->foreign('unit_id')->references('id')->on('product_units')->restrictOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['sale_id']);
            $table->foreign('sale_id')->references('id')->on('sales')->restrictOnDelete();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->foreign('customer_id')->references('id')->on('customers')->restrictOnDelete();
            $table->dropForeign(['prescription_id']);
            $table->foreign('prescription_id')->references('id')->on('prescriptions')->restrictOnDelete();
        });

        Schema::table('sale_return_lines', function (Blueprint $table) {
            $table->dropForeign(['sale_return_id']);
            $table->foreign('sale_return_id')->references('id')->on('sale_returns')->restrictOnDelete();
        });

        // Stock tables
        Schema::table('batch_stocks', function (Blueprint $table) {
            $table->dropForeign(['batch_id']);
            $table->foreign('batch_id')->references('id')->on('batches')->restrictOnDelete();
        });

        Schema::table('batches', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
        });

        Schema::table('stock_receipt_lines', function (Blueprint $table) {
            $table->dropForeign(['stock_receipt_id']);
            $table->foreign('stock_receipt_id')->references('id')->on('stock_receipts')->restrictOnDelete();
        });

        Schema::table('quarantine_records', function (Blueprint $table) {
            $table->dropForeign(['batch_id']);
            $table->foreign('batch_id')->references('id')->on('batches')->restrictOnDelete();
        });

        // Prescription tables
        Schema::table('prescription_lines', function (Blueprint $table) {
            $table->dropForeign(['prescription_id']);
            $table->foreign('prescription_id')->references('id')->on('prescriptions')->restrictOnDelete();
        });

        Schema::table('prescription_status_histories', function (Blueprint $table) {
            $table->dropForeign(['prescription_id']);
            $table->foreign('prescription_id')->references('id')->on('prescriptions')->restrictOnDelete();
        });

        Schema::table('dispensing_events', function (Blueprint $table) {
            $table->dropForeign(['prescription_line_id']);
            $table->foreign('prescription_line_id')->references('id')->on('prescription_lines')->restrictOnDelete();
            $table->dropForeign(['sale_id']);
            $table->foreign('sale_id')->references('id')->on('sales')->restrictOnDelete();
        });

        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropForeign(['verified_by']);
            $table->foreign('verified_by')->references('id')->on('users')->restrictOnDelete();
            $table->dropForeign(['rejected_by']);
            $table->foreign('rejected_by')->references('id')->on('users')->restrictOnDelete();
        });

        // Shift tables
        Schema::table('shift_cash_movements', function (Blueprint $table) {
            $table->dropForeign(['shift_id']);
            $table->foreign('shift_id')->references('id')->on('shifts')->restrictOnDelete();
        });

        // User-linked tables
        Schema::table('password_histories', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::table('identity_verifications', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->dropForeign(['admin_id']);
            $table->foreign('admin_id')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::table('daily_closings', function (Blueprint $table) {
            $table->dropForeign(['verified_by']);
            $table->foreign('verified_by')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::enableForeignKeyConstraints();

        // 2. Add CHECK constraints (STEP 3)
        AppendOnly::addCheckConstraint('batch_stocks', 'chk_batch_stocks_quantity', 'quantity >= 0', 'NEW.quantity >= 0');
        AppendOnly::addCheckConstraint('batch_stocks', 'chk_batch_stocks_status', "status IN ('sellable', 'quarantine')", "NEW.status IN ('sellable', 'quarantine')");

        AppendOnly::addCheckConstraint('sale_lines', 'chk_sale_lines_quantity', 'quantity > 0', 'NEW.quantity > 0');
        AppendOnly::addCheckConstraint('sale_lines', 'chk_sale_lines_subtotal', 'subtotal >= 0', 'NEW.subtotal >= 0');
        AppendOnly::addCheckConstraint('sale_lines', 'chk_sale_lines_unit_price', 'unit_price >= 0', 'NEW.unit_price >= 0');

        AppendOnly::addCheckConstraint('payments', 'chk_payments_amount', 'amount > 0', 'NEW.amount > 0');
        AppendOnly::addCheckConstraint('payments', 'chk_payments_method', "method IN ('cash', 'mobile_money', 'card', 'bank_transfer', 'telebirr', 'cbe_birr', 'credit')", "NEW.method IN ('cash', 'mobile_money', 'card', 'bank_transfer', 'telebirr', 'cbe_birr', 'credit')");

        AppendOnly::addCheckConstraint('sales', 'chk_sales_total', 'total >= 0', 'NEW.total >= 0');
        AppendOnly::addCheckConstraint('sales', 'chk_sales_status', "status IN ('completed', 'voided')", "NEW.status IN ('completed', 'voided')");

        AppendOnly::addCheckConstraint('stock_movements', 'chk_stock_movements_type', "type IN ('receipt', 'sale', 'sale_void', 'return', 'quarantine', 'adjustment', 'disposal', 'supplier_return', 'return_from_customer', 'return_quarantine', 'reversal')", "NEW.type IN ('receipt', 'sale', 'sale_void', 'return', 'quarantine', 'adjustment', 'disposal', 'supplier_return', 'return_from_customer', 'return_quarantine', 'reversal')");
        AppendOnly::addCheckConstraint('stock_movements', 'chk_stock_movements_stock_status', "stock_status IN ('sellable', 'quarantine')", "NEW.stock_status IN ('sellable', 'quarantine')");

        AppendOnly::addCheckConstraint('shifts', 'chk_shifts_status', "status IN ('open', 'closed')", "NEW.status IN ('open', 'closed')");
        AppendOnly::addCheckConstraint('shifts', 'chk_shifts_opening_float', 'opening_float >= 0', 'NEW.opening_float >= 0');

        AppendOnly::addCheckConstraint('daily_closings', 'chk_daily_closings_status', "status IN ('open', 'locked')", "NEW.status IN ('open', 'locked')");
        AppendOnly::addCheckConstraint('approval_requests', 'chk_approval_requests_status', "status IN ('pending', 'approved', 'rejected', 'expired', 'cancelled')", "NEW.status IN ('pending', 'approved', 'rejected', 'expired', 'cancelled')");
        AppendOnly::addCheckConstraint('held_sales', 'chk_held_sales_status', "status IN ('held', 'resumed', 'discarded')", "NEW.status IN ('held', 'resumed', 'discarded')");
        AppendOnly::addCheckConstraint('prescriptions', 'chk_prescriptions_status', "status IN ('registered', 'pending_verification', 'verified', 'partially_dispensed', 'fully_dispensed', 'rejected', 'cancelled', 'expired')", "NEW.status IN ('registered', 'pending_verification', 'verified', 'partially_dispensed', 'fully_dispensed', 'rejected', 'cancelled', 'expired')");

        // 3. Apply AppendOnly immutability triggers (STEP 2)
        // audit_logs: block both update and delete
        AppendOnly::create('audit_logs', blockUpdate: true, blockDelete: true);

        // controlled_drug_register: block both update and delete
        AppendOnly::create('controlled_drug_register', blockUpdate: true, blockDelete: true);

        // stock_movements: block both update and delete
        AppendOnly::create('stock_movements', blockUpdate: true, blockDelete: true);

        // approval_requests: delete only
        AppendOnly::create('approval_requests', blockUpdate: false, blockDelete: true);

        // sales: allow only status and reprint_count once completed; block delete once completed
        AppendOnly::create('sales', blockUpdate: true, blockDelete: true);

        // sale_lines: update and delete blocked once sale is completed
        AppendOnly::create('sale_lines', blockUpdate: true, blockDelete: true);

        // payments: update and delete blocked once sale is completed
        AppendOnly::create('payments', blockUpdate: true, blockDelete: true);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        AppendOnly::drop('payments');
        AppendOnly::drop('sale_lines');
        AppendOnly::drop('sales');
        AppendOnly::drop('approval_requests', dropUpdate: false, dropDelete: true);
        AppendOnly::drop('stock_movements');
        AppendOnly::drop('controlled_drug_register');
        AppendOnly::drop('audit_logs');

        AppendOnly::dropCheckConstraint('prescriptions', 'chk_prescriptions_status');
        AppendOnly::dropCheckConstraint('held_sales', 'chk_held_sales_status');
        AppendOnly::dropCheckConstraint('approval_requests', 'chk_approval_requests_status');
        AppendOnly::dropCheckConstraint('daily_closings', 'chk_daily_closings_status');
        AppendOnly::dropCheckConstraint('shifts', 'chk_shifts_opening_float');
        AppendOnly::dropCheckConstraint('shifts', 'chk_shifts_status');
        AppendOnly::dropCheckConstraint('stock_movements', 'chk_stock_movements_stock_status');
        AppendOnly::dropCheckConstraint('stock_movements', 'chk_stock_movements_type');
        AppendOnly::dropCheckConstraint('sales', 'chk_sales_status');
        AppendOnly::dropCheckConstraint('sales', 'chk_sales_total');
        AppendOnly::dropCheckConstraint('payments', 'chk_payments_method');
        AppendOnly::dropCheckConstraint('payments', 'chk_payments_amount');
        AppendOnly::dropCheckConstraint('sale_lines', 'chk_sale_lines_unit_price');
        AppendOnly::dropCheckConstraint('sale_lines', 'chk_sale_lines_subtotal');
        AppendOnly::dropCheckConstraint('sale_lines', 'chk_sale_lines_quantity');
        AppendOnly::dropCheckConstraint('batch_stocks', 'chk_batch_stocks_status');
        AppendOnly::dropCheckConstraint('batch_stocks', 'chk_batch_stocks_quantity');

        // Revert foreign keys
        Schema::disableForeignKeyConstraints();

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('sale_lines', function (Blueprint $table) {
            $table->dropForeign(['sale_id']);
            $table->foreign('sale_id')->references('id')->on('sales')->cascadeOnDelete();
            $table->dropForeign(['unit_id']);
            $table->foreign('unit_id')->references('id')->on('product_units')->nullOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['sale_id']);
            $table->foreign('sale_id')->references('id')->on('sales')->cascadeOnDelete();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->dropForeign(['prescription_id']);
            $table->foreign('prescription_id')->references('id')->on('prescriptions')->nullOnDelete();
        });

        Schema::table('sale_return_lines', function (Blueprint $table) {
            $table->dropForeign(['sale_return_id']);
            $table->foreign('sale_return_id')->references('id')->on('sale_returns')->cascadeOnDelete();
        });

        Schema::table('batch_stocks', function (Blueprint $table) {
            $table->dropForeign(['batch_id']);
            $table->foreign('batch_id')->references('id')->on('batches')->cascadeOnDelete();
        });

        Schema::table('batches', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });

        Schema::table('stock_receipt_lines', function (Blueprint $table) {
            $table->dropForeign(['stock_receipt_id']);
            $table->foreign('stock_receipt_id')->references('id')->on('stock_receipts')->cascadeOnDelete();
        });

        Schema::table('quarantine_records', function (Blueprint $table) {
            $table->dropForeign(['batch_id']);
            $table->foreign('batch_id')->references('id')->on('batches')->cascadeOnDelete();
        });

        Schema::table('prescription_lines', function (Blueprint $table) {
            $table->dropForeign(['prescription_id']);
            $table->foreign('prescription_id')->references('id')->on('prescriptions')->cascadeOnDelete();
        });

        Schema::table('prescription_status_histories', function (Blueprint $table) {
            $table->dropForeign(['prescription_id']);
            $table->foreign('prescription_id')->references('id')->on('prescriptions')->cascadeOnDelete();
        });

        Schema::table('dispensing_events', function (Blueprint $table) {
            $table->dropForeign(['prescription_line_id']);
            $table->foreign('prescription_line_id')->references('id')->on('prescription_lines')->cascadeOnDelete();
            $table->dropForeign(['sale_id']);
            $table->foreign('sale_id')->references('id')->on('sales')->nullOnDelete();
        });

        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropForeign(['verified_by']);
            $table->foreign('verified_by')->references('id')->on('users')->nullOnDelete();
            $table->dropForeign(['rejected_by']);
            $table->foreign('rejected_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('shift_cash_movements', function (Blueprint $table) {
            $table->dropForeign(['shift_id']);
            $table->foreign('shift_id')->references('id')->on('shifts')->cascadeOnDelete();
        });

        Schema::table('password_histories', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('identity_verifications', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->dropForeign(['admin_id']);
            $table->foreign('admin_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('daily_closings', function (Blueprint $table) {
            $table->dropForeign(['verified_by']);
            $table->foreign('verified_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::enableForeignKeyConstraints();
    }
};
