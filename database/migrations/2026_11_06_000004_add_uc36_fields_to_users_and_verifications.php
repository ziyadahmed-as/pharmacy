<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('password_reset_at')->nullable()->after('must_change_password');
            $table->string('account_status', 30)->default('active')->after('is_active');
            $table->index('account_status');
        });

        // Identity Verification Records for Audit and IAM Compliance
        Schema::create('identity_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->string('verification_method', 50); // in_person, registered_phone, approved_contact_channel
            $table->string('outcome', 20); // verified, failed
            $table->text('notes')->nullable(); // Non-sensitive context notes
            $table->timestamp('verified_at')->useCurrent();
            $table->timestamps();

            $table->index(['user_id', 'outcome']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('identity_verifications');

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['account_status']);
            $table->dropColumn(['password_reset_at', 'account_status']);
        });
    }
};

