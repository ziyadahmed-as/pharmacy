<?php

namespace App\Services;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;

class UserManagementService
{
    /**
     * Deactivate a user account, record security audit event, and invalidate active sessions.
     *
     * @param User $user The user account to deactivate
     * @param string $reason Reason for deactivation (e.g. employment_ended, security_incident)
     * @param array $extraMetadata Additional audit context
     * @return User
     */
    public function deactivateUser(User $user, string $reason = 'employment_ended', array $extraMetadata = []): User
    {
        DB::transaction(function () use ($user, $reason, $extraMetadata) {
            $user->update([
                'is_active' => false,
            ]);

            // Invalidate database sessions for this user if sessions table exists
            try {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            } catch (\Throwable $e) {
                // If session table is not in use or driver is different, ignore error
            }

            $metadata = array_merge([
                'username' => $user->username,
                'user_id' => $user->id,
                'reason' => $reason,
            ], $extraMetadata);

            AuditService::record(
                action: 'user_account_deactivated',
                category: 'security',
                description: "User account {$user->username} was deactivated. Reason: {$reason}",
                metadata: $metadata,
                user: $user
            );
        });

        return $user->fresh();
    }
}

