<?php

namespace App\Livewire\Settings;

use App\Models\ExpenseCategory;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Expense Categories')]
class ExpenseCategories extends Component
{
    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public bool $is_active = true;

    public bool $showModal = false;

    public function mount(): void
    {
        $user = Auth::user();
        abort_unless($user && $user->canAccessBusinessData() && $user->hasAnyRole(['pharmacy_manager', 'owner', 'system_admin']), 403);
    }

    public function openCreateModal(): void
    {
        $this->authorizeManage();
        $this->reset(['editingId', 'code', 'name']);
        $this->is_active = true;
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->authorizeManage();
        $cat = ExpenseCategory::findOrFail($id);
        $this->editingId = $cat->id;
        $this->code = $cat->code;
        $this->name = $cat->name;
        $this->is_active = $cat->is_active;
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
                Rule::unique('expense_categories', 'code')->ignore($this->editingId),
            ],
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ];

        $this->validate($rules);

        $payload = [
            'code' => strtolower(trim($this->code)),
            'name' => trim($this->name),
            'is_active' => $this->is_active,
        ];

        if ($this->editingId) {
            $cat = ExpenseCategory::findOrFail($this->editingId);
            $old = $cat->only(['code', 'name', 'is_active']);
            $cat->update($payload);

            AuditLogger::record(
                action: 'settings.expense_category.updated',
                subject: $cat,
                old: $old,
                new: $payload,
                user: $user,
                description: "Updated expense category {$cat->code}"
            );

            session()->flash('success', __('settings.expense_category_updated', ['default' => 'Expense category updated successfully.']));
        } else {
            $cat = ExpenseCategory::create($payload);

            AuditLogger::record(
                action: 'settings.expense_category.created',
                subject: $cat,
                new: $payload,
                user: $user,
                description: "Created expense category {$cat->code}"
            );

            session()->flash('success', __('settings.expense_category_created', ['default' => 'Expense category created successfully.']));
        }

        $this->showModal = false;
    }

    public function toggleActive(int $id): void
    {
        $this->authorizeManage();
        $user = Auth::user();
        $cat = ExpenseCategory::findOrFail($id);
        $oldState = $cat->is_active;
        $cat->update(['is_active' => ! $oldState]);

        AuditLogger::record(
            action: 'settings.expense_category.status_changed',
            subject: $cat,
            old: ['is_active' => $oldState],
            new: ['is_active' => ! $oldState],
            user: $user,
            description: ($oldState ? 'Deactivated' : 'Activated')." expense category {$cat->code}"
        );

        session()->flash('success', __('settings.expense_category_status_updated', ['default' => 'Expense category status updated.']));
    }

    private function authorizeManage(): void
    {
        $user = Auth::user();
        abort_unless($user && $user->canAccessBusinessData() && $user->hasAnyRole(['pharmacy_manager', 'owner']), 403);
    }

    public function render()
    {
        $categories = ExpenseCategory::withCount('expenses')
            ->orderBy('name')
            ->get();

        $canManage = Auth::user()?->canAccessBusinessData() && Auth::user()?->hasAnyRole(['pharmacy_manager', 'owner']);

        return view('livewire.settings.expense-categories', [
            'categories' => $categories,
            'canManage' => $canManage,
        ]);
    }
}
