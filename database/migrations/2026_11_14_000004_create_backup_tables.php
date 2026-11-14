<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_runs', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->string('disk')->default('backup');
            $table->string('type')->default('full'); // full, db
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('checksum')->nullable();
            $table->string('status')->default('in_progress'); // in_progress, success, failed
            $table->text('error_message')->nullable();
            $table->boolean('is_encrypted')->default(true);
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('restore_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('backup_run_id')->nullable()->constrained('backup_runs')->nullOnDelete();
            $table->string('backup_filename');
            $table->text('reason');
            $table->string('status')->default('pending'); // pending, approved, rejected, completed, failed
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approval_request_id')->nullable()->constrained('approval_requests')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->string('safety_backup_filename')->nullable();
            $table->text('error_message')->nullable();
            $table->json('log_entries')->nullable();
            $table->timestamp('restored_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restore_requests');
        Schema::dropIfExists('backup_runs');
    }
};
