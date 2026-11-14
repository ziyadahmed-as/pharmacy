<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adjustment_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('batches')->cascadeOnDelete();
            $table->string('reason_type'); // damage, loss, expiry_write_off, found_stock
            $table->integer('quantity'); // signed (+ for found_stock, - for damage, loss, expiry_write_off)
            $table->text('written_reason');
            $table->string('status')->default('pending_approval'); // pending_approval, approved, rejected, expired
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('second_person_id')->nullable()->constrained('users');
            $table->foreignId('approval_request_id')->nullable()->constrained('approval_requests')->nullOnDelete();
            $table->boolean('recount_done')->default(false);
            $table->foreignId('recounted_by')->nullable()->constrained('users');
            $table->timestamp('recounted_at')->nullable();
            $table->text('recount_notes')->nullable();
            $table->timestamps();

            $table->index(['batch_id', 'status']);
            $table->index(['requested_by', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adjustment_requests');
    }
};
