<?php

namespace App\Livewire\Auth;

use App\Actions\Auth\LogIn as LogInAction;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Login extends Component
{
    public string $username = '';

    public string $password = '';

    public bool $remember = false;

    protected function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string'],
        ];
    }

    public function login(LogInAction $logIn)
    {
        $this->validate();

        // Rate limit per username + IP, on top of the account lockout.
        $key = 'login:'.strtolower($this->username).'|'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 20)) {
            $this->addError('username', __('auth.too_many_attempts', ['seconds' => RateLimiter::availableIn($key)]));

            return;
        }

        try {
            $result = $logIn->execute($this->username, $this->password, $this->remember);
            RateLimiter::clear($key);

            return redirect()->to($result['redirect']);
        } catch (ValidationException $e) {
            RateLimiter::hit($key, 300);
            $this->reset('password');
            $this->addError('username', collect($e->errors())->flatten()->first());
        }
    }

    public function render()
    {
        return view('livewire.auth.login')
            ->layout('layouts.guest', ['title' => __('auth.login_title')]);
    }
}
