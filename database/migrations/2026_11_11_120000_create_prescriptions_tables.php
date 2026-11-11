<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * UC-19: Register prescription
     * FR-4.2, 4.8, 4.11.
     */
    public function up(): void
    {
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->string('prescription_number', 64)->unique();
            $table->foreignId('customer_id')->constrained('customers');
            $table->string('prescriber_name');
            $table->string('prescriber_registration_no', 64);
            $table->date('prescription_date');
            $table->enum('status', [
                'registered',
                'pending_verification',
                'verified',
                'partially_dispensed',
                'fully_dispensed',
                'rejected',
                'cancelled',
                'expired',
            ])->default('pending_verification');
            $table->string('scan_path')->nullable();
            $table->foreignId('replaced_by_id')->nullable()->constrained('prescriptions')->nullOnDelete();
            $table->string('cancellation_reason')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['customer_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('prescription_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->constrained('prescriptions')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->string('dose');
            $table->unsignedInteger('quantity_prescribed');
            $table->unsignedInteger('quantity_dispensed')->default(0);
            $table->timestamps();

            $table->index(['prescription_id', 'product_id']);
        });

        Schema::create('prescription_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->constrained('prescriptions')->cascadeOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->foreignId('user_id')->constrained('users');
            $table->string('role_used', 64)->nullable();
            $table->string('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['prescription_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prescription_status_histories');
        Schema::dropIfExists('prescription_lines');
        Schema::dropIfExists('prescriptions');
    }
};

