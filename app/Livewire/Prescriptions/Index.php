<?php

namespace App\Livewire\Prescriptions;

use App\Enums\Role;
use App\Models\Prescription;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Prescriptions')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'status')]
    public string $statusFilter = '';

    public function mount(): void
    {
        $user = Auth::user();
        abort_if(! $user || $user->hasRole(Role::SystemAdmin), 403);
        abort_unless($user->hasPermission('prescriptions.view'), 403);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $prescriptions = Prescription::query()
            ->with(['customer', 'creator'])
            ->when($this->search, function ($q) {
                $q->where(function ($inner) {
                    $inner->where('prescription_number', 'like', "%{$this->search}%")
                        ->orWhere('prescriber_name', 'like', "%{$this->search}%")
                        ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$this->search}%"));
                });
            })
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->latest('id')
            ->paginate(15);

        return view('livewire.prescriptions.index', [
            'prescriptions' => $prescriptions,
            'canRegister' => Auth::user()?->hasPermission('prescriptions.register') ?? false,
            'statuses' => [
                Prescription::STATUS_REGISTERED,
                Prescription::STATUS_PENDING_VERIFICATION,
                Prescription::STATUS_VERIFIED,
                Prescription::STATUS_PARTIALLY_DISPENSED,
                Prescription::STATUS_FULLY_DISPENSED,
                Prescription::STATUS_REJECTED,
                Prescription::STATUS_CANCELLED,
                Prescription::STATUS_EXPIRED,
            ],
        ]);
    }
}
