<?php

namespace App\Livewire\Auth;

use App\Actions\Auth\CompleteLogin;
use App\Actions\Auth\VerifyTwoFactor;
use App\Models\User;
use Livewire\Attributes\Locked;
use Livewire\Component;

class TwoFactorChallenge extends Component
{
    public string $code = '';

    #[Locked]
    public ?int $pendingUserId = null;

    public function mount()
    {
        $this->pendingUserId = session()->get('login.mfa_user_id');

        if (! $this->pendingUserId || ! User::find($this->pendingUserId)) {
            session()->forget(['login.mfa_user_id', 'login.remember']);

            return redirect()->route('login');
        }
    }

    public function verify(VerifyTwoFactor $verify, CompleteLogin $complete)
    {
        $this->validate(['code' => ['required', 'digits:6']]);

        $user = User::find(session()->get('login.mfa_user_id'));
        if (! $user) {
            return redirect()->route('login');
        }

        if (! $verify->execute($user, $this->code)) {
            $this->reset('code');
            $this->addError('code', __('auth.mfa_invalid'));

            return;
        }

        $remember = (bool) session()->get('login.remember', false);
        session()->forget(['login.mfa_user_id', 'login.remember']);

        return redirect()->to($complete->execute($user, $remember)['redirect']);
    }

    public function cancel()
    {
        session()->forget(['login.mfa_user_id', 'login.remember']);

        return redirect()->route('login');
    }

    public function render()
    {
        return view('livewire.auth.two-factor-challenge')
            ->layout('layouts.guest', ['title' => __('auth.mfa_title')]);
    }
}
