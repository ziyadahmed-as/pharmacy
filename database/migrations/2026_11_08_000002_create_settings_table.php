<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 50)->index();
            $table->string('key', 120)->unique();       // full dotted key: group.name
            $table->json('value')->nullable();          // null => "Not set"
            $table->string('type', 30);                 // int, bool, string, int_list, string_list, currency, ...
            $table->string('description')->nullable();
            $table->boolean('is_sensitive')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('setting_histories', function (Blueprint $table) {
            $table->id();
            $table->string('key', 120)->index();
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setting_histories');
        Schema::dropIfExists('settings');
    }
};
