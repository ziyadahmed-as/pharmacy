<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->string('type', 80)->index();
            $table->nullableMorphs('subject');
            $table->json('payload')->nullable();
            $table->string('summary', 500);
            $table->foreignId('initiator_id')->constrained('users');
            $table->string('initiator_role', 40)->nullable();
            $table->foreignId('concerns_user_id')->nullable()->constrained('users');
            $table->json('eligible_roles');                       // roles allowed to decide
            $table->foreignId('approver_id')->nullable()->constrained('users');
            $table->string('approver_role', 40)->nullable();
            $table->string('status', 20)->default('pending')->index(); // pending|approved|rejected|expired|cancelled
            $table->text('initiator_comment')->nullable();
            $table->text('decision_comment')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
        });

        // One-time reveal of a temporary password to the System Administrator (UC-35 step 3).
        // The value is encrypted (never plain text) and the row is deleted when revealed.
        Schema::create('credential_reveals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('for_user_id')->constrained('users');      // who may reveal it (the SA who requested)
            $table->foreignId('account_id')->constrained('users');       // the account the password belongs to
            $table->text('secret');                                      // encrypted
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        Schema::create('role_assignment_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->json('old_roles');
            $table->json('new_roles');
            $table->foreignId('actor_id')->nullable()->constrained('users');
            $table->foreignId('approver_id')->nullable()->constrained('users');
            $table->string('reason', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_assignment_history');
        Schema::dropIfExists('credential_reveals');
        Schema::dropIfExists('approval_requests');
    }
};
