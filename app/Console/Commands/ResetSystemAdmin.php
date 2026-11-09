<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\EndUserSessions;
use App\Services\PasswordPolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Break-glass for a locked-out SA (UC-36 step 6). Runs on the server only, is audited
 * with terminal `cli`, and prints the temporary password once.
 *
 *   php artisan pms:reset-sa {username}
 */
class ResetSystemAdmin extends Command
{
    protected $signature = 'pms:reset-sa {username : Username of the system administrator} {--note= : Why this was needed}';

    protected $description = 'Break-glass: reset and unlock a system administrator account (server side, audited)';

    public function handle(EndUserSessions $endSessions): int
    {
        $user = User::where('username', $this->argument('username'))->first();

        if (! $user || ! $user->hasRole(Role::SystemAdmin)) {
            $this->error('No system administrator with that username.');

            return self::FAILURE;
        }

        $temporary = Str::password(16, symbols: false);
        PasswordPolicy::set($user, $temporary, mustChange: true);
        $user->forceFill(['locked_at' => null, 'failed_login_attempts' => 0, 'is_active' => true, 'account_status' => User::STATUS_ACTIVE, 'password_reset_at' => now()])->save();
        $endSessions($user);

        AuditLogger::record('user.password.reset_cli', $user, [], [], [
            'note' => $this->option('note') ?: 'break-glass', 'channel' => 'artisan',
        ], $user);

        $this->warn("Temporary password for {$user->username} (shown once): {$temporary}");

        return self::SUCCESS;
    }
}
