<?php

namespace App\Livewire\Auth;

use App\Services\ActingRole;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/** UC-33 step 7: pick the role to act under; stored in the session and used by AuditLogger. */
class ChooseActingRole extends Component
{
    public function choose(string $role)
    {
        $user = Auth::user();
        $before = ActingRole::for($user)?->value;

        if (! ActingRole::choose($user, $role)) {
            return;
        }

        AuditLogger::record('auth.acting_role.chosen', null, ['role' => $before], ['role' => $role]);

        return redirect()->route('dashboard');
    }

    public function render()
    {
        return view('livewire.auth.choose-acting-role', ['roles' => Auth::user()->roleEnums()])
            ->layout('layouts.app', ['title' => __('auth.choose_role_title')]);
    }
}
