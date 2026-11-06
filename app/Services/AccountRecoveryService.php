<?php

namespace App\Services;

use App\Models\IdentityVerification;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class AccountRecoveryService
{
    /**
     * Record an identity verification event before performing recovery operations.
     */
    public function recordIdentityVerification(
        User $targetUser,
        User $adminUser,
        string $method,
        string $outcome,
        ?string $notes = null
    ): IdentityVerification {
        $allowedMethods = ['in_person', 'registered_phone', 'approved_contact_channel'];
        if (! in_array($method, $allowedMethods, true)) {
            throw new InvalidArgumentException("Invalid identity verification method: {$method}");
        }

        $allowedOutcomes = ['verified', 'failed'];
        if (! in_array($outcome, $allowedOutcomes, true)) {
            throw new InvalidArgumentException("Invalid verification outcome: {$outcome}");
        }

        $verification = IdentityVerification::create([
            'user_id' => $targetUser->id,
            'admin_id' => $adminUser->id,
            'verification_method' => $method,
            'outcome' => $outcome,
            'notes' => $notes,
            'verified_at' => now(),
        ]);

        if ($outcome === 'failed') {
            AuditService::record(
                action: 'identity_verification_failed',
                category: 'security',
                description: "Identity verification failed for user {$targetUser->username}. Operation refused.",
                metadata: [
                    'username' => $targetUser->username,
                    'admin_username' => $adminUser->username,
                    'method' => $method,
                ],
                user: $adminUser
            );
        } else {
            AuditService::record(
                action: 'identity_verification_success',
                category: 'security',
                description: "Identity verified for user {$targetUser->username} via {$method}.",
                metadata: [
                    'username' => $targetUser->username,
                    'admin_username' => $adminUser->username,
                    'method' => $method,
                ],
                user: $adminUser
            );
        }

        return $verification;
    }

    /**
     * Unlock an account that is currently locked.
     */
    public function unlockAccount(User $targetUser, User $adminUser, IdentityVerification $verification): User
    {
        if (! $verification->isVerified() || $verification->user_id !== $targetUser->id) {
            throw new RuntimeException('Cannot unlock account without confirmed identity verification.');
        }

        if (! $targetUser->isEligibleForUnlock()) {
            throw new RuntimeException("Account {$targetUser->username} with status '{$targetUser->status}' cannot be unlocked.");
        }

        DB::transaction(function () use ($targetUser, $adminUser, $verification) {
            $previousFailedAttempts = $targetUser->failed_login_attempts;
            $lockedAt = $targetUser->locked_at;

            $targetUser->update([
                'locked_at' => null,
                'failed_login_attempts' => 0,
            ]);

            AuditService::record(
                action: 'account_unlocked_by_admin',
                category: 'security',
                description: "Account {$targetUser->username} unlocked by administrator {$adminUser->username}.",
                metadata: [
                    'username' => $targetUser->username,
                    'admin_username' => $adminUser->username,
                    'previous_failed_attempts' => $previousFailedAttempts,
                    'was_locked_at' => (string) $lockedAt,
                    'verification_id' => $verification->id,
                ],
                user: $adminUser
            );
        });

        return $targetUser->fresh();
    }

    /**
     * Reset password for an eligible account, generate secure temporary password, and force change at next login.
     *
     * @return array{user: User, temporary_password: string}
     */
    public function resetPassword(User $targetUser, User $adminUser, IdentityVerification $verification): array
    {
        if (! $verification->isVerified() || $verification->user_id !== $targetUser->id) {
            throw new RuntimeException('Cannot reset password without confirmed identity verification.');
        }

        if (! $targetUser->isEligibleForPasswordReset()) {
            throw new RuntimeException("Account {$targetUser->username} with status '{$targetUser->status}' is not eligible for password reset.");
        }

        // Generate cryptographically secure temporary password (14 characters with letters, numbers, symbols)
        $temporaryPassword = Str::password(14, letters: true, numbers: true, symbols: true, spaces: false);

        DB::transaction(function () use ($targetUser, $adminUser, $temporaryPassword, $verification) {
            $targetUser->update([
                'password' => Hash::make($temporaryPassword),
                'must_change_password' => true,
                'password_reset_at' => now(),
            ]);

            // Invalidate all active sessions for this user across devices
            try {
                DB::table('sessions')->where('user_id', $targetUser->id)->delete();
            } catch (\Throwable $e) {
                // If sessions table driver is different, continue
            }

            AuditService::record(
                action: 'password_reset_by_admin',
                category: 'security',
                description: "Password reset for user {$targetUser->username} by administrator {$adminUser->username}. Forced change required on next login.",
                metadata: [
                    'username' => $targetUser->username,
                    'admin_username' => $adminUser->username,
                    'verification_id' => $verification->id,
                    'must_change_password' => true,
                ],
                user: $adminUser
            );
        });

        return [
            'user' => $targetUser->fresh(),
            'temporary_password' => $temporaryPassword,
        ];
    }

    /**
     * Atomically Reset Password AND Unlock a locked account.
     *
     * @return array{user: User, temporary_password: string}
     */
    public function resetPasswordAndUnlock(User $targetUser, User $adminUser, IdentityVerification $verification): array
    {
        if (! $verification->isVerified() || $verification->user_id !== $targetUser->id) {
            throw new RuntimeException('Cannot perform operation without confirmed identity verification.');
        }

        if (! $targetUser->isEligibleForUnlock()) {
            throw new RuntimeException("Account {$targetUser->username} is not locked.");
        }

        $temporaryPassword = Str::password(14, letters: true, numbers: true, symbols: true, spaces: false);

        DB::transaction(function () use ($targetUser, $adminUser, $temporaryPassword, $verification) {
            $previousFailedAttempts = $targetUser->failed_login_attempts;
            $lockedAt = $targetUser->locked_at;

            $targetUser->update([
                'password' => Hash::make($temporaryPassword),
                'must_change_password' => true,
                'password_reset_at' => now(),
                'locked_at' => null,
                'failed_login_attempts' => 0,
            ]);

            // Invalidate active sessions
            try {
                DB::table('sessions')->where('user_id', $targetUser->id)->delete();
            } catch (\Throwable $e) {
                // Ignore if sessions table not used
            }

            AuditService::record(
                action: 'account_reset_and_unlocked_by_admin',
                category: 'security',
                description: "Account {$targetUser->username} unlocked and password reset by administrator {$adminUser->username}.",
                metadata: [
                    'username' => $targetUser->username,
                    'admin_username' => $adminUser->username,
                    'previous_failed_attempts' => $previousFailedAttempts,
                    'was_locked_at' => (string) $lockedAt,
                    'verification_id' => $verification->id,
                    'must_change_password' => true,
                ],
                user: $adminUser
            );
        });

        return [
            'user' => $targetUser->fresh(),
            'temporary_password' => $temporaryPassword,
        ];
    }
}

