<?php

namespace App\Livewire\Auth;

use App\Services\AuthenticationService;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Login extends Component
{
    public string $username = '';
    public string $password = '';
    public bool $remember = false;
    public bool $showPassword = false;

    protected function rules(): array
    {
        return [
            'username' => ['required', 'string', 'min:3', 'max:50'],
            'password' => ['required', 'string'],
        ];
    }

    public function togglePasswordVisibility(): void
    {
        $this->showPassword = ! $this->showPassword;
    }

    public function login(AuthenticationService $authService)
    {
        $this->validate();

        try {
            $result = $authService->attempt($this->username, $this->password, $this->remember);

            return redirect()->to($result['redirect']);
        } catch (ValidationException $e) {
            $this->addError('username', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.auth.login')
            ->layout('layouts.guest', ['title' => 'Sign In - Pharmacy Management System']);
    }
}

