<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stocktakes', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('full'); // full | partial
            $table->string('scope_type')->nullable(); // 'location' | 'category' | null (for full)
            $table->string('scope_value')->nullable(); // actual location name or category id
            $table->string('status')->default('draft'); // draft | counting | review | approved | rejected
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('created_by');
        });

        Schema::create('stocktake_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stocktake_id')->constrained('stocktakes')->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained('batches')->cascadeOnDelete();
            $table->integer('system_qty');   // snapshot at the time of stocktake creation
            $table->integer('counted_qty')->nullable();
            $table->integer('variance')->nullable();   // counted_qty - system_qty
            $table->integer('recount_qty')->nullable();
            $table->foreignId('counted_by')->nullable()->constrained('users');
            $table->foreignId('second_person_id')->nullable()->constrained('users');
            $table->timestamp('counted_at')->nullable();
            $table->timestamps();

            $table->index(['stocktake_id', 'batch_id']);
            $table->unique(['stocktake_id', 'batch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stocktake_lines');
        Schema::dropIfExists('stocktakes');
    }
};

