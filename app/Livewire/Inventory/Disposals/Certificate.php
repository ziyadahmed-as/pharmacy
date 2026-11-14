<?php

namespace App\Livewire\Inventory\Disposals;

use App\Enums\Role;
use App\Models\Disposal;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Disposal Certificate')]
class Certificate extends Component
{
    public Disposal $disposal;

    public function mount(Disposal $disposal): void
    {
        $user = Auth::user();
        abort_if(! $user || $user->hasRole(Role::SystemAdmin), 403);
        abort_unless($user->hasPermission('stock.dispose'), 403);

        $this->disposal = $disposal->load([
            'batch.product',
            'requestedBy',
            'performer',
            'witness',
            'approvedBy',
        ]);
    }

    public function render(): View
    {
        return view('livewire.inventory.disposals.certificate', [
            'disposal' => $this->disposal,
        ]);
    }
}

