<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Report Favourites per user (STEP 1)
        Schema::create('report_favourites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('report_key', 80);
            $table->timestamps();

            $table->unique(['user_id', 'report_key']);
        });

        // 2. Report Execution Log (Recently run per user) (STEP 1)
        Schema::create('report_runs_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('report_key', 80);
            $table->json('filters')->nullable();
            $table->integer('row_count')->default(0);
            $table->decimal('duration_ms', 8, 2)->default(0);
            $table->timestamp('run_at')->useCurrent();

            $table->index(['user_id', 'run_at']);
        });

        // 3. Saved Filters per user per report (STEP 3)
        Schema::create('report_saved_filters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('report_key', 80);
            $table->string('name', 100);
            $table->json('filters');
            $table->timestamps();

            $table->unique(['user_id', 'report_key', 'name']);
        });

        // 4. Report Schedules (Owner daily/weekly/monthly email) (STEP 5)
        Schema::create('report_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('report_key', 80);
            $table->string('frequency', 20); // daily, weekly, monthly
            $table->string('format', 10)->default('pdf'); // pdf, csv, excel
            $table->json('recipient_emails'); // list of emails or user IDs
            $table->json('filters')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'next_run_at']);
        });

        // 5. Queued Report Exports (large export job results) (STEP 4)
        Schema::create('report_queued_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('report_key', 80);
            $table->string('format', 10);
            $table->json('filters')->nullable();
            $table->string('status', 20)->default('pending'); // pending, processing, completed, failed
            $table->string('file_path')->nullable();
            $table->string('filename')->nullable();
            $table->integer('row_count')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_queued_exports');
        Schema::dropIfExists('report_schedules');
        Schema::dropIfExists('report_saved_filters');
        Schema::dropIfExists('report_runs_log');
        Schema::dropIfExists('report_favourites');
    }
};
