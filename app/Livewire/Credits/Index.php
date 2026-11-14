<?php

namespace App\Livewire\Credits;

use App\Enums\Role;
use App\Models\CreditAccount;
use Livewire\Component;

/**
 * Credit accounts list (PM/Owner only).
 *
 * @uc UC-26
 */
class Index extends Component
{
    public string $search = '';

    public function mount(): void
    {
        abort_unless(
            auth()->user()?->hasAnyRole([Role::PharmacyManager, Role::Owner]),
            403
        );
    }

    public function render()
    {
        $accounts = CreditAccount::with('customer')
            ->when($this->search, function ($q) {
                $q->whereHas('customer', function ($cq) {
                    $cq->where('name', 'like', "%{$this->search}%")
                        ->orWhere('phone', 'like', "%{$this->search}%");
                });
            })
            ->orderBy('updated_at', 'desc')
            ->paginate(20);

        return view('livewire.credits.index', [
            'accounts' => $accounts,
        ])->layout('layouts.app');
    }
}
