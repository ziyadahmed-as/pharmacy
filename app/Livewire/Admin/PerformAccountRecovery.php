<?php

namespace App\Livewire\Admin;

use App\Models\IdentityVerification;
use App\Models\User;
use App\Services\AccountRecoveryService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PerformAccountRecovery extends Component
{
    public ?User $targetUser = null;
    public string $search = '';

    // Step state
    public int $step = 1; // 1: Select User, 2: Verify Identity, 3: Select Action, 4: Reveal Temporary Password

    // Verification Form Fields
    public string $verification_method = 'in_person';
    public string $verification_outcome = 'verified';
    public string $verification_notes = '';
    public ?IdentityVerification $recordedVerification = null;

    // One-Time Temporary Password Reveal
    public ?string $oneTimePassword = null;
    public string $completedAction = '';

    protected function rules(): array
    {
        return [
            'verification_method' => ['required', 'in:in_person,registered_phone,approved_contact_channel'],
            'verification_outcome' => ['required', 'in:verified,failed'],
            'verification_notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function mount(?int $userId = null)
    {
        if ($userId) {
            $user = User::find($userId);
            if ($user) {
                $this->selectUser($user->id);
            }
        }
    }

    public function selectUser(int $id)
    {
        $this->targetUser = User::findOrFail($id);
        $this->step = 2; // Move to Identity Verification
    }

    public function submitIdentityVerification(AccountRecoveryService $recoveryService)
    {
        $this->validate();

        $admin = Auth::user();

        $this->recordedVerification = $recoveryService->recordIdentityVerification(
            targetUser: $this->targetUser,
            adminUser: $admin,
            method: $this->verification_method,
            outcome: $this->verification_outcome,
            notes: $this->verification_notes ?: null
        );

        if ($this->verification_outcome === 'failed') {
            session()->flash('error', "Identity verification failed for user '{$this->targetUser->username}'. In accordance with IAM policy, no unlock or password reset may be performed.");
            return redirect()->route('admin.account-recovery');
        }

        $this->step = 3; // Move to Select Action
    }

    public function unlockAccountOnly(AccountRecoveryService $recoveryService)
    {
        $admin = Auth::user();

        try {
            $recoveryService->unlockAccount($this->targetUser, $admin, $this->recordedVerification);

            session()->flash('status', "Account '{$this->targetUser->username}' has been successfully unlocked. The user may now authenticate with their existing credentials.");
            return redirect()->route('admin.account-recovery');
        } catch (\Exception $e) {
            $this->addError('action', $e->getMessage());
        }
    }

    public function resetPasswordOnly(AccountRecoveryService $recoveryService)
    {
        $admin = Auth::user();

        try {
            $result = $recoveryService->resetPassword($this->targetUser, $admin, $this->recordedVerification);

            $this->oneTimePassword = $result['temporary_password'];
            $this->completedAction = 'password_reset';
            $this->step = 4; // Reveal credential
        } catch (\Exception $e) {
            $this->addError('action', $e->getMessage());
        }
    }

    public function resetPasswordAndUnlockCombined(AccountRecoveryService $recoveryService)
    {
        $admin = Auth::user();

        try {
            $result = $recoveryService->resetPasswordAndUnlock($this->targetUser, $admin, $this->recordedVerification);

            $this->oneTimePassword = $result['temporary_password'];
            $this->completedAction = 'reset_and_unlocked';
            $this->step = 4; // Reveal credential
        } catch (\Exception $e) {
            $this->addError('action', $e->getMessage());
        }
    }

    public function finish()
    {
        // Obliterate temporary password from memory/component state
        $this->oneTimePassword = null;
        return redirect()->route('admin.account-recovery');
    }

    public function render()
    {
        $searchResults = [];
        if ($this->step === 1 && strlen($this->search) >= 2) {
            $searchResults = User::where('username', 'like', "%{$this->search}%")
                ->orWhere('name', 'like', "%{$this->search}%")
                ->take(10)
                ->get();
        }

        return view('livewire.admin.perform-account-recovery', [
            'searchResults' => $searchResults,
        ])->layout('layouts.app', ['title' => 'Process Account Recovery - PMS Admin']);
    }
}

