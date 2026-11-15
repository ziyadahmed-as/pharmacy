<?php

namespace App\Livewire\Settings;

use App\Models\StorageLocation;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Storage Locations')]
class Locations extends Component
{
    public ?int $editingLocationId = null;

    public string $code = '';

    public string $name = '';

    public string $type = StorageLocation::TYPE_SHELF;

    public bool $is_active = true;

    public bool $showModal = false;

    public function mount(): void
    {
        $user = Auth::user();
        abort_unless($user && $user->hasPermission('settings.view'), 403);
    }

    public function openCreateModal(): void
    {
        $this->authorizeManage();
        $this->reset(['editingLocationId', 'code', 'name']);
        $this->type = StorageLocation::TYPE_SHELF;
        $this->is_active = true;
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function edit(int $locationId): void
    {
        $this->authorizeManage();
        $location = StorageLocation::findOrFail($locationId);
        $this->editingLocationId = $location->id;
        $this->code = $location->code;
        $this->name = $location->name;
        $this->type = $location->type;
        $this->is_active = $location->is_active;
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorizeManage();
        $user = Auth::user();

        $rules = [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('storage_locations', 'code')->ignore($this->editingLocationId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(StorageLocation::TYPES)],
            'is_active' => ['boolean'],
        ];

        $this->validate($rules);

        if ($this->editingLocationId) {
            $location = StorageLocation::findOrFail($this->editingLocationId);
            $old = $location->only(['code', 'name', 'type', 'is_active']);

            $location->update([
                'code' => strtoupper(trim($this->code)),
                'name' => trim($this->name),
                'type' => $this->type,
                'is_active' => $this->is_active,
            ]);

            AuditLogger::record(
                action: 'settings.location.updated',
                subject: $location,
                old: $old,
                new: $location->only(['code', 'name', 'type', 'is_active']),
                user: $user,
                description: "Updated storage location {$location->code}"
            );

            session()->flash('success', __('locations.updated_successfully', ['default' => 'Storage location updated successfully.']));
        } else {
            $location = StorageLocation::create([
                'code' => strtoupper(trim($this->code)),
                'name' => trim($this->name),
                'type' => $this->type,
                'is_active' => $this->is_active,
            ]);

            AuditLogger::record(
                action: 'settings.location.created',
                subject: $location,
                new: $location->only(['code', 'name', 'type', 'is_active']),
                user: $user,
                description: "Created storage location {$location->code}"
            );

            session()->flash('success', __('locations.created_successfully', ['default' => 'Storage location created successfully.']));
        }

        $this->showModal = false;
    }

    public function toggleActive(int $locationId): void
    {
        $this->authorizeManage();
        $user = Auth::user();
        $location = StorageLocation::findOrFail($locationId);
        $oldState = $location->is_active;
        $location->update(['is_active' => ! $oldState]);

        AuditLogger::record(
            action: 'settings.location.status_changed',
            subject: $location,
            old: ['is_active' => $oldState],
            new: ['is_active' => ! $oldState],
            user: $user,
            description: ($oldState ? 'Deactivated' : 'Activated')." storage location {$location->code}"
        );

        session()->flash('success', __('locations.status_updated_successfully', ['default' => 'Location status updated.']));
    }

    private function authorizeManage(): void
    {
        $user = Auth::user();
        abort_unless($user && $user->hasAnyRole(['system_admin', 'pharmacy_manager', 'owner']), 403);
    }

    public function render()
    {
        $locations = StorageLocation::withCount('batchStocks')
            ->orderBy('name')
            ->get();

        $canManage = Auth::user()?->hasAnyRole(['system_admin', 'pharmacy_manager', 'owner']);

        return view('livewire.settings.locations', [
            'locations' => $locations,
            'canManage' => $canManage,
            'types' => StorageLocation::TYPES,
        ]);
    }
}
