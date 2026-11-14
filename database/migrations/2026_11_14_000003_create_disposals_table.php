<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('batches')->cascadeOnDelete();
            $table->foreignId('quarantine_record_id')->constrained('quarantine_records')->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('reason'); // expired, damaged, recalled, contaminated, other
            $table->string('method'); // return_to_supplier, incineration, authorised_collector, other
            $table->string('supplier_name')->nullable();
            $table->string('collector_name')->nullable();
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('performer_id')->nullable()->constrained('users');
            $table->foreignId('witness_id')->nullable()->constrained('users');
            $table->foreignId('approval_request_id')->nullable()->constrained('approval_requests')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->foreignId('rejected_by')->nullable()->constrained('users');
            $table->text('rejection_reason')->nullable();
            $table->string('certificate_no')->nullable()->unique();
            $table->string('evidence_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('disposed_at')->nullable();
            $table->timestamps();

            $table->index(['batch_id', 'status']);
            $table->index(['quarantine_record_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disposals');
    }
};
