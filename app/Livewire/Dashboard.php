<?php

namespace App\Livewire;

use App\Models\ApprovalRequest;
use App\Services\ActingRole;
use App\Services\SettingsRepository;
use App\Support\SettingsCatalogue;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/** Dashboard placeholder with a role-based menu (UC-33 step 9) and the "Not set" banner (UC-38 step 5). */
class Dashboard extends Component
{
    public function render(SettingsRepository $settings)
    {
        $user = Auth::user()->load('roles');

        $missing = [];
        if ($user->hasAnyRole(['owner', 'pharmacy_manager'])) {
            foreach (SettingsCatalogue::all() as $key => $def) {
                if ($def['required'] && $settings->isNotSet($key)) {
                    $missing[] = $key;
                }
            }
        }

        $pendingApprovals = $user->hasPermission('approvals.process')
            ? ApprovalRequest::query()->decidableBy($user)->count()
            : 0;

        return view('livewire.dashboard', [
            'user' => $user,
            'actingRole' => ActingRole::for($user),
            'missingSettings' => $missing,
            'pendingApprovals' => $pendingApprovals,
        ])->layout('layouts.app', ['title' => __('nav.dashboard')]);
    }
}
