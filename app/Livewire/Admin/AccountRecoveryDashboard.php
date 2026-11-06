<?php

namespace App\Livewire\Admin;

use App\Models\AuditLog;
use App\Models\IdentityVerification;
use App\Models\User;
use Livewire\Component;

class AccountRecoveryDashboard extends Component
{
    public string $search = '';

    public function render()
    {
        // 1. Locked Accounts needing attention
        $lockedAccounts = User::whereNotNull('locked_at')
            ->where('is_active', true)
            ->when($this->search, function ($query) {
                $query->where('username', 'like', '%' . $this->search . '%')
                    ->orWhere('name', 'like', '%' . $this->search . '%');
            })
            ->latest('locked_at')
            ->take(10)
            ->get();

        // 2. Recent identity verifications
        $recentVerifications = IdentityVerification::with(['user', 'administrator'])
            ->latest()
            ->take(8)
            ->get();

        // 3. Recent Audit recovery events
        $recoveryLogs = AuditLog::whereIn('action', [
            'account_unlocked_by_admin',
            'password_reset_by_admin',
            'account_reset_and_unlocked_by_admin',
            'identity_verification_failed',
            'identity_verification_success',
        ])
        ->latest('id')
        ->take(10)
        ->get();

        return view('livewire.admin.account-recovery-dashboard', [
            'lockedAccounts' => $lockedAccounts,
            'recentVerifications' => $recentVerifications,
            'recoveryLogs' => $recoveryLogs,
        ])->layout('layouts.app', ['title' => 'Account Recovery & Identity Verification - PMS Admin']);
    }
}

