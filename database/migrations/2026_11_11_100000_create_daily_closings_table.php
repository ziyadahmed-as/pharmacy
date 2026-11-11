<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_closings', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('status', 20)->default('locked'); // 'open', 'locked'
            $table->json('system_totals_by_method');
            $table->json('counted_totals_by_method');
            $table->json('variances_by_method');
            $table->json('variance_reasons')->nullable();
            $table->foreignId('closed_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_closings');
    }
};
