<?php

namespace App\Services;

use App\Models\CredentialReveal;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;

/**
 * Hands a temporary password to the SA exactly once. The secret is stored encrypted
 * (never plain text, never in the audit log) and deleted on reveal or expiry.
 */
class CredentialRevealService
{
    public function store(User $forUser, User $account, string $password): CredentialReveal
    {
        return CredentialReveal::create([
            'for_user_id' => $forUser->id,
            'account_id' => $account->id,
            'secret' => Crypt::encryptString($password),
            'expires_at' => now()->addHours(72),
        ]);
    }

    /** @return array{username: string, password: string}|null */
    public function reveal(int $id, User $viewer): ?array
    {
        $row = CredentialReveal::where('id', $id)->where('for_user_id', $viewer->id)->first();
        if (! $row || $row->expires_at->isPast()) {
            $row?->delete();

            return null;
        }

        $password = Crypt::decryptString($row->secret);
        $username = User::whereKey($row->account_id)->value('username');
        $row->delete();
        AuditLogger::record('user.password.revealed', null, [], [], ['account_id' => $row->account_id], $viewer);

        return ['username' => $username, 'password' => $password];
    }
}
