<?php

namespace App\Livewire\Prescriptions;

use App\Enums\Role;
use App\Models\Prescription;
use App\Services\PrescriptionSearch;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Advanced prescription search and patient prescription history viewer.
 *
 * @uc UC-23
 *
 * @fr FR-4.12, FR-5.6, FR-7.4
 *
 * @ac AC-26
 */
#[Layout('layouts.app')]
#[Title('Prescription Search & History')]
class Search extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $patient = '';

    #[Url]
    public string $prescriber = '';

    #[Url]
    public string $drug = '';

    #[Url(as: 'from')]
    public string $dateFrom = '';

    #[Url(as: 'to')]
    public string $dateTo = '';

    #[Url]
    public string $status = '';

    #[Url(as: 'suspect')]
    public bool $suspectOnly = false;

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

    public function updatingPatient(): void
    {
        $this->resetPage();
    }

    public function updatingPrescriber(): void
    {
        $this->resetPage();
    }

    public function updatingDrug(): void
    {
        $this->resetPage();
    }

    public function updatingDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatingDateTo(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingSuspectOnly(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'patient', 'prescriber', 'drug', 'dateFrom', 'dateTo', 'status', 'suspectOnly']);
        $this->resetPage();
    }

    public function render(): View
    {
        $filters = [
            'q' => $this->search,
            'patient' => $this->patient,
            'prescriber' => $this->prescriber,
            'drug' => $this->drug,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'status' => $this->status,
            'suspect_only' => $this->suspectOnly,
        ];

        $prescriptions = PrescriptionSearch::build($filters)->paginate(15);

        $user = Auth::user();
        $canExport = $user && $user->hasAnyRole([Role::PharmacyManager, Role::Owner]);

        return view('livewire.prescriptions.search', [
            'prescriptions' => $prescriptions,
            'canExport' => $canExport,
            'statusOptions' => [
                Prescription::STATUS_REGISTERED => __('prescriptions.status_registered'),
                Prescription::STATUS_PENDING_VERIFICATION => __('prescriptions.status_pending_verification'),
                Prescription::STATUS_VERIFIED => __('prescriptions.status_verified'),
                Prescription::STATUS_PARTIALLY_DISPENSED => __('prescriptions.status_partially_dispensed'),
                Prescription::STATUS_FULLY_DISPENSED => __('prescriptions.status_fully_dispensed'),
                Prescription::STATUS_REJECTED => __('prescriptions.status_rejected'),
                Prescription::STATUS_CANCELLED => __('prescriptions.status_cancelled'),
                Prescription::STATUS_EXPIRED => __('prescriptions.status_expired'),
            ],
        ]);
    }
}
