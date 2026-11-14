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
#[Title('Disposal Details')]
class Show extends Component
{
    public Disposal $disposal;

    public function mount(Disposal $disposal): void
    {
        $user = Auth::user();
        abort_if(! $user || $user->hasRole(Role::SystemAdmin), 403);
        abort_unless($user->hasPermission('stock.dispose'), 403);

        $this->disposal = $disposal->load([
            'batch.product',
            'quarantineRecord',
            'requestedBy',
            'performer',
            'witness',
            'approvedBy',
            'rejectedBy',
            'approvalRequest',
        ]);
    }

    public function render(): View
    {
        $user = Auth::user();
        $isManager = $user && ($user->hasRole(Role::PharmacyManager) || $user->hasRole(Role::Owner));

        return view('livewire.inventory.disposals.show', [
            'disposal' => $this->disposal,
            'isManager' => $isManager,
            'user' => $user,
        ]);
    }
}

