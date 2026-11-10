<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_imports', function (Blueprint $table) {
            $table->id();
            $table->string('file_path');
            $table->string('file_name');
            $table->enum('status', ['pending', 'validating', 'validated', 'confirmed', 'cancelled', 'failed'])->default('pending');
            $table->json('mapping')->nullable();
            $table->json('totals')->nullable(); // total_rows, valid_rows, error_rows
            $table->foreignId('user_id')->constrained('users');
            $table->timestamps();
        });

        Schema::create('product_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_import_id')->constrained('product_imports')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('raw_data');
            $table->json('errors')->nullable();
            $table->enum('status', ['valid', 'error', 'imported', 'skipped'])->default('valid');
            $table->timestamps();

            $table->index(['product_import_id', 'status']);
            $table->index(['product_import_id', 'row_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_import_rows');
        Schema::dropIfExists('product_imports');
    }
};
