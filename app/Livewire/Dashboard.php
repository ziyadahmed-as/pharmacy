<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Dashboard extends Component
{
    public function render()
    {
        $user = Auth::user()->load('roles');

        return view('livewire.dashboard', [
            'user' => $user,
        ])->layout('layouts.app', ['title' => 'Dashboard - PMS']);
    }
}
