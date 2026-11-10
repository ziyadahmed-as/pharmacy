<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tills', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('till_id')->constrained('tills')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('opened_at');
            $table->unsignedInteger('opening_float')->default(0); // in santim (minor units)
            $table->string('status', 20)->default('open'); // 'open', 'closed'
            $table->timestamp('closed_at')->nullable();
            $table->json('expected_cash_per_payment_method')->nullable();
            $table->json('counted_cash_per_payment_method')->nullable();
            $table->integer('variance')->nullable(); // in santim
            $table->string('variance_reason')->nullable();
            $table->timestamps();

            // Enforce ONE open shift per till and per user
            $table->unsignedBigInteger('open_till_id')->nullable()->storedAs("CASE WHEN status = 'open' THEN till_id ELSE NULL END");
            $table->unique('open_till_id', 'unique_open_shift_per_till');

            $table->unsignedBigInteger('open_user_id')->nullable()->storedAs("CASE WHEN status = 'open' THEN user_id ELSE NULL END");
            $table->unique('open_user_id', 'unique_open_shift_per_user');

            $table->index(['till_id', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('tills');
    }
};
