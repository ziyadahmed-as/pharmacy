<?php

namespace App\Livewire\Prescribers;

use App\Enums\Role;
use App\Models\Prescriber;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Prescribers')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $statusFilter = 'all';

    public bool $showModal = false;

    public ?int $editingPrescriberId = null;

    public string $name = '';

    public string $licence_number = '';

    public string $facility = '';

    public string $phone = '';

    public bool $is_active = true;

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

    public function openCreateModal(): void
    {
        $this->authorizeManage();
        $this->reset(['editingPrescriberId', 'name', 'licence_number', 'facility', 'phone']);
        $this->is_active = true;
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function edit(int $prescriberId): void
    {
        $this->authorizeManage();
        $prescriber = Prescriber::findOrFail($prescriberId);
        $this->editingPrescriberId = $prescriber->id;
        $this->name = $prescriber->name;
        $this->licence_number = (string) ($prescriber->licence_number ?? '');
        $this->facility = (string) ($prescriber->facility ?? '');
        $this->phone = (string) ($prescriber->phone ?? '');
        $this->is_active = (bool) $prescriber->is_active;
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorizeManage();

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'licence_number' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('prescribers', 'licence_number')->ignore($this->editingPrescriberId),
            ],
            'facility' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'is_active' => ['boolean'],
        ];

        $this->validate($rules);

        $user = Auth::user();
        $cleanLicence = trim($this->licence_number) !== '' ? trim($this->licence_number) : null;
        $cleanFacility = trim($this->facility) !== '' ? trim($this->facility) : null;
        $cleanPhone = trim($this->phone) !== '' ? trim($this->phone) : null;

        if ($this->editingPrescriberId) {
            $prescriber = Prescriber::findOrFail($this->editingPrescriberId);
            $old = $prescriber->only(['name', 'licence_number', 'facility', 'phone', 'is_active']);

            $prescriber->update([
                'name' => trim($this->name),
                'licence_number' => $cleanLicence,
                'facility' => $cleanFacility,
                'phone' => $cleanPhone,
                'is_active' => $this->is_active,
            ]);

            AuditLogger::record(
                action: 'prescriber.updated',
                subject: $prescriber,
                old: $old,
                new: $prescriber->only(['name', 'licence_number', 'facility', 'phone', 'is_active']),
                user: $user,
                description: "Updated prescriber {$prescriber->name}"
            );

            session()->flash('success', __('prescribers.prescriber_updated_successfully', ['default' => 'Prescriber updated successfully.']));
        } else {
            $prescriber = Prescriber::create([
                'name' => trim($this->name),
                'licence_number' => $cleanLicence,
                'facility' => $cleanFacility,
                'phone' => $cleanPhone,
                'is_active' => $this->is_active,
            ]);

            AuditLogger::record(
                action: 'prescriber.created',
                subject: $prescriber,
                old: [],
                new: $prescriber->only(['name', 'licence_number', 'facility', 'phone', 'is_active']),
                user: $user,
                description: "Created prescriber {$prescriber->name}"
            );

            session()->flash('success', __('prescribers.prescriber_created_successfully', ['default' => 'Prescriber created successfully.']));
        }

        $this->showModal = false;
    }

    public function toggleActive(int $prescriberId): void
    {
        $this->authorizeManage();
        $user = Auth::user();
        $prescriber = Prescriber::findOrFail($prescriberId);
        $oldState = $prescriber->is_active;
        $prescriber->update(['is_active' => ! $oldState]);

        AuditLogger::record(
            action: 'prescriber.status_changed',
            subject: $prescriber,
            old: ['is_active' => $oldState],
            new: ['is_active' => ! $oldState],
            user: $user,
            description: ($oldState ? 'Deactivated' : 'Activated')." prescriber {$prescriber->name}"
        );

        session()->flash('success', __('prescribers.status_updated_successfully', ['default' => 'Prescriber status updated.']));
    }

    private function authorizeManage(): void
    {
        $user = Auth::user();
        abort_if(! $user || $user->hasRole(Role::SystemAdmin), 403);
        abort_unless($user->hasPermission('prescriptions.register'), 403);
    }

    public function render(): View
    {
        $query = Prescriber::withCount('prescriptions')
            ->search($this->search);

        if ($this->statusFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($this->statusFilter === 'inactive') {
            $query->where('is_active', false);
        }

        $prescribers = $query->orderBy('name')->paginate(15);
        $canManage = Auth::user()?->hasPermission('prescriptions.register');

        return view('livewire.prescribers.index', [
            'prescribers' => $prescribers,
            'canManage' => $canManage,
        ]);
    }
}
