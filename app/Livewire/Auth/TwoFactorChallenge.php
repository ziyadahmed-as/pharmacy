<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Services\AuthenticationService;
use Livewire\Component;

class TwoFactorChallenge extends Component
{
    public string $code = '';
    public ?User $pendingUser = null;

    protected function rules(): array
    {
        return [
            'code' => ['required', 'string', 'size:6', 'regex:/^[0-9]+$/'],
        ];
    }

    public function mount()
    {
        $userId = session()->get('login.mfa_user_id');

        if (! $userId) {
            return redirect()->route('login');
        }

        $this->pendingUser = User::find($userId);

        if (! $this->pendingUser) {
            session()->forget('login.mfa_user_id');
            return redirect()->route('login');
        }
    }

    public function verify(AuthenticationService $authService)
    {
        $this->validate();

        if (! $this->pendingUser) {
            return redirect()->route('login');
        }

        if (! $authService->verifyMfa($this->pendingUser, $this->code)) {
            $this->addError('code', 'The provided verification code is invalid.');
            return;
        }

        $remember = session()->get('login.remember', false);
        session()->forget(['login.mfa_user_id', 'login.remember']);

        $result = $authService->loginUser($this->pendingUser, $remember);

        return redirect()->to($result['redirect']);
    }

    public function cancel()
    {
        session()->forget(['login.mfa_user_id', 'login.remember']);
        return redirect()->route('login');
    }

    public function render()
    {
        return view('livewire.auth.two-factor-challenge')
            ->layout('layouts.guest', ['title' => 'Two-Factor Verification - PMS']);
    }
}
