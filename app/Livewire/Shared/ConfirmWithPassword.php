<?php

namespace App\Livewire\Shared;

use App\Services\AuditLogger;
use App\Services\ReAuthentication;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Reusable re-authentication modal (S0 step 6).
 *
 * Any component opens it with:
 *   $this->dispatch('confirm-password', purpose: 'approval.decide');
 * and reacts to the `password-confirmed` event, then calls
 * ReAuthentication::consume($purpose) before doing the sensitive work.
 */
class ConfirmWithPassword extends Component
{
    public bool $open = false;

    public string $purpose = '';

    public string $password = '';

    #[On('confirm-password')]
    public function ask(string $purpose): void
    {
        $this->reset('password');
        $this->resetErrorBag();
        $this->purpose = $purpose;
        $this->open = true;
    }

    public function confirm(): void
    {
        $user = Auth::user();
        $key = 'reauth:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('password', __('auth.too_many_attempts', ['seconds' => RateLimiter::availableIn($key)]));

            return;
        }

        if (! Hash::check($this->password, $user->password)) {
            RateLimiter::hit($key, 60);
            AuditLogger::record('auth.reauth.failed', null, [], [], ['purpose' => $this->purpose]);
            $this->addError('password', __('auth.reauth_failed'));

            return;
        }

        RateLimiter::clear($key);
        ReAuthentication::grant($this->purpose);

        $purpose = $this->purpose;
        $this->reset(['open', 'password', 'purpose']);
        $this->dispatch('password-confirmed', purpose: $purpose);
    }

    public function cancel(): void
    {
        $this->reset(['open', 'password', 'purpose']);
        $this->resetErrorBag();
    }

    public function render()
    {
        return view('livewire.shared.confirm-with-password');
    }
}
