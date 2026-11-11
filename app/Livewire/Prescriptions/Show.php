<?php

namespace App\Livewire\Prescriptions;

use App\Enums\Role;
use App\Models\Prescription;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Prescription Details')]
class Show extends Component
{
    public Prescription $prescription;

    public function mount(Prescription $prescription): void
    {
        $user = Auth::user();
        abort_if(! $user || $user->hasRole(Role::SystemAdmin), 403);
        abort_unless($user->hasPermission('prescriptions.view'), 403);

        $this->prescription = $prescription;
    }

    public function statusBadgeClass(): string
    {
        return match ($this->prescription->status) {
            Prescription::STATUS_REGISTERED => 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300 border-sky-200 dark:border-sky-800',
            Prescription::STATUS_PENDING_VERIFICATION => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 border-amber-200 dark:border-amber-800',
            Prescription::STATUS_VERIFIED => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
            Prescription::STATUS_PARTIALLY_DISPENSED => 'bg-violet-100 text-violet-800 dark:bg-violet-950 dark:text-violet-300 border-violet-200 dark:border-violet-800',
            Prescription::STATUS_FULLY_DISPENSED => 'bg-teal-100 text-teal-800 dark:bg-teal-950 dark:text-teal-300 border-teal-200 dark:border-teal-800',
            Prescription::STATUS_REJECTED => 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300 border-rose-200 dark:border-rose-800',
            Prescription::STATUS_CANCELLED => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border-slate-200 dark:border-slate-700',
            Prescription::STATUS_EXPIRED => 'bg-orange-100 text-orange-800 dark:bg-orange-950 dark:text-orange-300 border-orange-200 dark:border-orange-800',
            default => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border-slate-200 dark:border-slate-700',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->prescription->status) {
            Prescription::STATUS_REGISTERED => __('prescriptions.status_registered'),
            Prescription::STATUS_PENDING_VERIFICATION => __('prescriptions.status_pending_verification'),
            Prescription::STATUS_VERIFIED => __('prescriptions.status_verified'),
            Prescription::STATUS_PARTIALLY_DISPENSED => __('prescriptions.status_partially_dispensed'),
            Prescription::STATUS_FULLY_DISPENSED => __('prescriptions.status_fully_dispensed'),
            Prescription::STATUS_REJECTED => __('prescriptions.status_rejected'),
            Prescription::STATUS_CANCELLED => __('prescriptions.status_cancelled'),
            Prescription::STATUS_EXPIRED => __('prescriptions.status_expired'),
            default => $this->prescription->status,
        };
    }

    public function render(): View
    {
        $this->prescription->load(['lines.product', 'customer', 'statusHistory.user', 'replacedBy']);

        return view('livewire.prescriptions.show', [
            'statusBadgeClass' => $this->statusBadgeClass(),
            'statusLabel' => $this->statusLabel(),
        ]);
    }
}
