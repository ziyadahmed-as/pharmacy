<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('photo_path')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('address')->nullable();
            $table->text('id_number')->nullable();              // encrypted cast (BR-13, FR-10.1)
            $table->string('title')->nullable();
            $table->date('hire_date')->nullable();
            $table->string('status', 20)->default('active')->index();   // active|on_leave|ended
            $table->date('end_date')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 40)->nullable();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users');
            $table->timestamps();
        });

        Schema::create('staff_licences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff');
            $table->string('number');
            $table->string('issuing_body');
            $table->string('category')->nullable();             // free text (OQ-09)
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->index();
            $table->timestamps();                               // old rows are kept as history
            $table->index(['staff_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_licences');
        Schema::dropIfExists('staff');
    }
};
