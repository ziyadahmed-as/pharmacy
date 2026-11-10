<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quarantine_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('batches')->cascadeOnDelete();
            $table->integer('quantity'); // base units quarantined
            $table->enum('reason', ['expired', 'damaged', 'recalled', 'contaminated', 'other']);
            $table->enum('status', ['open', 'released', 'disposed', 'returned'])->default('open');
            $table->foreignId('created_by')->constrained('users');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['batch_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quarantine_records');
    }
};
