<?php

namespace App\Livewire\Settings;

use App\Models\PaymentMethod;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Payment Methods')]
class PaymentMethods extends Component
{
    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public bool $is_cash = false;

    public bool $is_active = true;

    public bool $requires_reference = false;

    public bool $showModal = false;

    public function mount(): void
    {
        $user = Auth::user();
        abort_unless($user && $user->canAccessBusinessData() && $user->hasAnyRole(['pharmacy_manager', 'owner', 'system_admin']), 403);
    }

    public function openCreateModal(): void
    {
        $this->authorizeManage();
        $this->reset(['editingId', 'code', 'name', 'is_cash', 'requires_reference']);
        $this->is_active = true;
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->authorizeManage();
        $pm = PaymentMethod::findOrFail($id);
        $this->editingId = $pm->id;
        $this->code = $pm->code;
        $this->name = $pm->name;
        $this->is_cash = $pm->is_cash;
        $this->is_active = $pm->is_active;
        $this->requires_reference = $pm->requires_reference;
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
                Rule::unique('payment_methods', 'code')->ignore($this->editingId),
            ],
            'name' => ['required', 'string', 'max:100'],
            'is_cash' => ['boolean'],
            'is_active' => ['boolean'],
            'requires_reference' => ['boolean'],
        ];

        $this->validate($rules);

        $payload = [
            'code' => strtolower(trim($this->code)),
            'name' => trim($this->name),
            'is_cash' => $this->is_cash,
            'is_active' => $this->is_active,
            'requires_reference' => $this->requires_reference,
        ];

        if ($this->editingId) {
            $pm = PaymentMethod::findOrFail($this->editingId);
            $old = $pm->only(['code', 'name', 'is_cash', 'is_active', 'requires_reference']);
            $pm->update($payload);

            AuditLogger::record(
                action: 'settings.payment_method.updated',
                subject: $pm,
                old: $old,
                new: $payload,
                user: $user,
                description: "Updated payment method {$pm->code}"
            );

            session()->flash('success', __('settings.payment_method_updated', ['default' => 'Payment method updated successfully.']));
        } else {
            $pm = PaymentMethod::create($payload);

            AuditLogger::record(
                action: 'settings.payment_method.created',
                subject: $pm,
                new: $payload,
                user: $user,
                description: "Created payment method {$pm->code}"
            );

            session()->flash('success', __('settings.payment_method_created', ['default' => 'Payment method created successfully.']));
        }

        $this->showModal = false;
    }

    public function toggleActive(int $id): void
    {
        $this->authorizeManage();
        $user = Auth::user();
        $pm = PaymentMethod::findOrFail($id);
        $oldState = $pm->is_active;
        $pm->update(['is_active' => ! $oldState]);

        AuditLogger::record(
            action: 'settings.payment_method.status_changed',
            subject: $pm,
            old: ['is_active' => $oldState],
            new: ['is_active' => ! $oldState],
            user: $user,
            description: ($oldState ? 'Deactivated' : 'Activated')." payment method {$pm->code}"
        );

        session()->flash('success', __('settings.payment_method_status_updated', ['default' => 'Payment method status updated.']));
    }

    private function authorizeManage(): void
    {
        $user = Auth::user();
        abort_unless($user && $user->canAccessBusinessData() && $user->hasAnyRole(['pharmacy_manager', 'owner']), 403);
    }

    public function render()
    {
        $methods = PaymentMethod::withCount('payments')
            ->orderBy('name')
            ->get();

        $canManage = Auth::user()?->canAccessBusinessData() && Auth::user()?->hasAnyRole(['pharmacy_manager', 'owner']);

        return view('livewire.settings.payment-methods', [
            'methods' => $methods,
            'canManage' => $canManage,
        ]);
    }
}
