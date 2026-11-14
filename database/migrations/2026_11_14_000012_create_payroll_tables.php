<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. salary_profiles
        Schema::create('salary_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->unique()->constrained('staff')->cascadeOnDelete();
            $table->unsignedInteger('base_salary'); // in cents/subunits or standard integer amount
            $table->unsignedInteger('allowances')->default(0);
            $table->unsignedInteger('hourly_rate')->default(0); // for overtime
            $table->string('payment_method')->default('bank_transfer'); // bank_transfer, cash, cheque
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 2. salary_advances
        Schema::create('salary_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->unsignedInteger('amount');
            $table->date('request_date');
            $table->string('reason')->nullable();
            $table->string('status')->default('approved'); // pending, approved, recovered, cancelled
            $table->string('repayment_month')->nullable(); // YYYY-MM
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('payroll_run_id')->nullable(); // set once recovered
            $table->timestamps();
        });

        // 3. payroll_runs
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->string('month', 7); // YYYY-MM
            $table->string('status')->default('draft'); // draft, pending_approval, approved, paid, rejected
            $table->unsignedInteger('total_gross')->default(0);
            $table->unsignedInteger('total_allowances')->default(0);
            $table->unsignedInteger('total_overtime')->default(0);
            $table->unsignedInteger('total_deductions')->default(0);
            $table->unsignedInteger('total_advances')->default(0);
            $table->unsignedInteger('total_net')->default(0);
            $table->foreignId('prepared_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('approval_request_id')->nullable()->constrained('approval_requests')->nullOnDelete();
            $table->foreignId('expense_id')->nullable()->constrained('expenses')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['month']);
        });

        // 4. payroll_lines
        Schema::create('payroll_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->unsignedInteger('base_salary')->default(0);
            $table->unsignedInteger('allowances')->default(0);
            $table->decimal('overtime_hours', 8, 2)->default(0);
            $table->unsignedInteger('overtime_amount')->default(0);
            $table->unsignedInteger('gross')->default(0);
            $table->unsignedInteger('deductions')->default(0);
            $table->unsignedInteger('advances')->default(0);
            $table->unsignedInteger('net')->default(0);
            $table->string('payment_method')->default('bank_transfer');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['payroll_run_id', 'staff_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_lines');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('salary_advances');
        Schema::dropIfExists('salary_profiles');
    }
};

