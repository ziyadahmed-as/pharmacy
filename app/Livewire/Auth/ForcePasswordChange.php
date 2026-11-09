<?php

namespace App\Livewire\Auth;

use App\Actions\Auth\ChangePassword;
use App\Services\PasswordPolicy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class ForcePasswordChange extends Component
{
    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount()
    {
        if (! Auth::user()?->must_change_password) {
            return redirect()->route('dashboard');
        }
    }

    public function updatePassword(ChangePassword $change)
    {
        $this->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed'],
        ]);

        try {
            $change->execute(Auth::user(), $this->current_password, $this->password);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }
            $this->reset(['current_password', 'password', 'password_confirmation']);

            return;
        }

        session()->flash('status', __('auth.password_changed'));

        return redirect()->route('dashboard');
    }

    public function render()
    {
        return view('livewire.auth.force-password-change', ['min' => PasswordPolicy::minLength()])
            ->layout('layouts.app', ['title' => __('auth.force_change_title')]);
    }
}
