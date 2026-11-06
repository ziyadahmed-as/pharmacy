<?php

namespace App\Livewire\Auth;

use App\Services\AuditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

class ForcePasswordChange extends Component
{
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    protected function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ];
    }

    public function mount()
    {
        $user = Auth::user();

        if (! $user || ! $user->must_change_password) {
            return redirect()->route('dashboard');
        }
    }

    public function updatePassword()
    {
        $this->validate();

        $user = Auth::user();

        if (! Hash::check($this->current_password, $user->password)) {
            $this->addError('current_password', 'The current password provided is incorrect.');
            return;
        }

        $user->update([
            'password' => Hash::make($this->password),
            'must_change_password' => false,
        ]);

        AuditService::record(
            action: 'password_change_forced_completed',
            category: 'security',
            description: 'User successfully completed forced password change',
            metadata: ['username' => $user->username],
            user: $user
        );

        session()->flash('status', 'Password successfully changed. You may now continue using the system.');

        return redirect()->route('dashboard');
    }

    public function render()
    {
        return view('livewire.auth.force-password-change')
            ->layout('layouts.app', ['title' => 'Password Change Required']);
    }
}

