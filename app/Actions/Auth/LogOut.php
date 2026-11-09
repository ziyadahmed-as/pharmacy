<?php

namespace App\Actions\Auth;

use App\Services\AuditLogger;
use Illuminate\Support\Facades\Auth;

class LogOut
{
    public function execute(string $reason = 'manual'): void
    {
        $user = Auth::user();

        if ($user) {
            AuditLogger::record($reason === 'idle' ? 'auth.logout.idle' : 'auth.logout.manual', $user, [], [], [], $user);
        }

        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
    }
}
