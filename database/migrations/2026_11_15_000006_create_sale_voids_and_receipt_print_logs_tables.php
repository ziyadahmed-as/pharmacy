<?php

use App\Support\AppendOnly;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. sale_voids table
        Schema::create('sale_voids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->unique()->constrained('sales')->restrictOnDelete();
            $table->text('reason');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approval_request_id')->nullable()->constrained('approval_requests')->nullOnDelete();
            $table->timestamp('voided_at')->useCurrent();
            $table->timestamps();
        });

        AppendOnly::create('sale_voids', blockUpdate: true, blockDelete: true);

        // 2. receipt_print_logs table
        Schema::create('receipt_print_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('printed_at')->useCurrent();
            $table->boolean('is_reprint')->default(false);
            $table->string('reason', 500)->nullable();
            $table->timestamps();

            $table->index(['sale_id', 'is_reprint']);
        });

        AppendOnly::create('receipt_print_logs', blockUpdate: true, blockDelete: true);
    }

    public function down(): void
    {
        AppendOnly::drop('receipt_print_logs');
        Schema::dropIfExists('receipt_print_logs');

        AppendOnly::drop('sale_voids');
        Schema::dropIfExists('sale_voids');
    }
};
