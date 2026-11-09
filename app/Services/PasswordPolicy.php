<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Minimum length and no reuse of the last N passwords (UC-33 step 5).
 * N and the minimum come from settings (security.password_history).
 *
 * @uc UC-33 @fr FR-8.6 @ac AC-18
 */
final class PasswordPolicy
{
    public static function minLength(): int
    {
        return max(8, (int) setting('security.password_min_length', 8));
    }

    /** @return list<string> error messages, empty when the password is acceptable */
    public static function violations(User $user, string $password): array
    {
        $errors = [];

        if (mb_strlen($password) < self::minLength()) {
            $errors[] = __('auth.password_too_short', ['min' => self::minLength()]);
        }

        if (self::wasUsedRecently($user, $password)) {
            $errors[] = __('auth.password_reused', ['count' => self::historySize()]);
        }

        return $errors;
    }

    public static function historySize(): int
    {
        return max(1, (int) setting('security.password_history', 5));
    }

    public static function wasUsedRecently(User $user, string $password): bool
    {
        if ($user->password && Hash::check($password, $user->password)) {
            return true;
        }

        // the CURRENT password counts as one of the last N, so N-1 archived hashes remain
        $hashes = DB::table('password_histories')
            ->where('user_id', $user->getKey())
            ->orderByDesc('id')
            ->limit(max(0, self::historySize() - 1))
            ->pluck('password_hash');

        foreach ($hashes as $hash) {
            if (Hash::check($password, $hash)) {
                return true;
            }
        }

        return false;
    }

    /** Push the user's CURRENT hash into history before it is replaced. */
    public static function archiveCurrent(User $user): void
    {
        if (! $user->password) {
            return;
        }
        DB::table('password_histories')->insert([
            'user_id' => $user->getKey(),
            'password_hash' => $user->password,
            'created_at' => now(),
        ]);
    }

    /** Set a new password: archive the old hash, stamp the change time. */
    public static function set(User $user, string $newPassword, bool $mustChange = false): void
    {
        self::archiveCurrent($user);

        $user->forceFill([
            'password' => $newPassword,
            'must_change_password' => $mustChange,
            'password_changed_at' => now(),
        ])->save();
    }
}
