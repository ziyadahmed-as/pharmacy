<?php

namespace App\Livewire\Tills;

use App\Models\Till;
use App\Services\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Manage Tills')]
class Index extends Component
{
    public string $newTillName = '';

    public ?string $errorMessage = null;

    public function mount(): void
    {
        abort_unless(Auth::user()?->hasPermission('tills.manage'), 403);
    }

    public function createTill(): void
    {
        $this->errorMessage = null;

        $this->validate([
            'newTillName' => ['required', 'string', 'max:150', 'unique:tills,name'],
        ]);

        $till = Till::create([
            'name' => trim($this->newTillName),
            'is_active' => true,
        ]);

        AuditLogger::record(
            action: 'till.created',
            subject: $till,
            old: [],
            new: ['name' => $till->name, 'is_active' => true],
            user: Auth::user(),
            description: "Till {$till->name} created"
        );

        $this->reset('newTillName');
        session()->flash('status', __('shifts.till_created_successfully'));
    }

    public function toggleActive(int $tillId): void
    {
        $till = Till::findOrFail($tillId);

        // Cannot deactivate a till that has an active open shift
        if ($till->is_active && $till->hasOpenShift()) {
            $this->errorMessage = __('shifts.cannot_deactivate_till_with_open_shift');

            return;
        }

        $oldActive = $till->is_active;
        $till->update(['is_active' => ! $oldActive]);

        AuditLogger::record(
            action: 'till.status_updated',
            subject: $till,
            old: ['is_active' => $oldActive],
            new: ['is_active' => $till->is_active],
            user: Auth::user(),
            description: "Till {$till->name} active status toggled to ".($till->is_active ? 'active' : 'inactive')
        );

        session()->flash('status', __('shifts.till_updated_successfully'));
    }

    public function render(): View
    {
        $tills = Till::with(['activeShift.user'])
            ->orderBy('name')
            ->get();

        return view('livewire.tills.index', [
            'tills' => $tills,
        ]);
    }
}
