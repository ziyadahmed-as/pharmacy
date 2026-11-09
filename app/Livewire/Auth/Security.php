<?php

namespace App\Livewire\Auth;

use App\Actions\Auth\ChangePassword;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Totp;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Own-account security: optional two-factor sign-in (UC-33 step 6) and password change.
 * Two-factor is never forced and is not offered to pharmacy_staff.
 */
class Security extends Component
{
    public string $pendingSecret = '';

    public string $code = '';

    public string $disablePassword = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function startTwoFactor(): void
    {
        abort_unless($this->canUseTwoFactor(), 403);
        $this->pendingSecret = Totp::generateSecret();
        $this->reset('code');
    }

    public function confirmTwoFactor(): void
    {
        abort_unless($this->canUseTwoFactor(), 403);
        $this->validate(['code' => ['required', 'digits:6']]);

        if (! $this->pendingSecret || ! Totp::verify($this->pendingSecret, $this->code)) {
            $this->addError('code', __('auth.mfa_invalid'));

            return;
        }

        $user = Auth::user();
        $user->forceFill(['two_factor_enabled' => true, 'two_factor_secret' => $this->pendingSecret])->save();
        AuditLogger::record('auth.mfa.enabled', $user);

        $this->reset(['pendingSecret', 'code']);
        session()->flash('status', __('auth.mfa_enabled'));
    }

    public function disableTwoFactor(): void
    {
        $user = Auth::user();

        if (! Hash::check($this->disablePassword, $user->password)) {
            $this->addError('disablePassword', __('auth.reauth_failed'));

            return;
        }

        $user->forceFill(['two_factor_enabled' => false, 'two_factor_secret' => null])->save();
        AuditLogger::record('auth.mfa.disabled', $user);
        $this->reset('disablePassword');
        session()->flash('status', __('auth.mfa_disabled'));
    }

    public function changePassword(ChangePassword $change): void
    {
        $this->validate([
            'current_password' => ['required'],
            'password' => ['required', 'confirmed'],
        ]);

        try {
            $change->execute(Auth::user(), $this->current_password, $this->password);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }

            return;
        }

        $this->reset(['current_password', 'password', 'password_confirmation']);
        session()->flash('status', __('auth.password_changed'));
    }

    private function canUseTwoFactor(): bool
    {
        foreach (Auth::user()->roleEnums() as $role) {
            if ($role->allowsMfa()) {
                return true;
            }
        }

        return false;
    }

    public function render()
    {
        /** @var User $user */
        $user = Auth::user();

        return view('livewire.auth.security', [
            'canUseTwoFactor' => $this->canUseTwoFactor(),
            'enabled' => $user->two_factor_enabled,
            'uri' => $this->pendingSecret ? Totp::provisioningUri($this->pendingSecret, $user->username) : null,
        ])->layout('layouts.app', ['title' => __('nav.security')]);
    }
}
