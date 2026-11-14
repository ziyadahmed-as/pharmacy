<?php

namespace App\Livewire\Finance\Expenses;

use App\Actions\Finance\RecordExpense;
use App\Enums\Role;
use App\Models\Expense;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Operating Expenses')]
class Index extends Component
{
    use WithFileUploads, WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $categoryFilter = '';

    #[Url]
    public string $statusFilter = '';

    #[Url]
    public string $fromDate = '';

    #[Url]
    public string $toDate = '';

    // Create Modal state
    public bool $showCreateModal = false;

    public string $category = Expense::CATEGORY_RENT;

    public string $amount = '';

    public string $date = '';

    public string $description = '';

    public $proofFile = null;

    // Reverse Modal state
    public bool $showReverseModal = false;

    public ?int $reversingExpenseId = null;

    public string $reversalReason = '';

    public ?string $errorMessage = null;

    public ?string $successMessage = null;

    public function mount(): void
    {
        $user = Auth::user();
        abort_if(! $user || $user->hasRole(Role::SystemAdmin), 403);
        abort_unless($user->hasPermission('finance.expense.view'), 403);

        $this->date = now()->toDateString();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingFromDate(): void
    {
        $this->resetPage();
    }

    public function updatingToDate(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->resetCreateForm();
        $this->showCreateModal = true;
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
        $this->resetCreateForm();
    }

    public function openReverseModal(int $id): void
    {
        $this->errorMessage = null;
        $this->reversingExpenseId = $id;
        $this->reversalReason = '';
        $this->showReverseModal = true;
    }

    public function closeReverseModal(): void
    {
        $this->showReverseModal = false;
        $this->reversingExpenseId = null;
        $this->reversalReason = '';
    }

    public function saveExpense(RecordExpense $action): void
    {
        $this->errorMessage = null;
        $this->successMessage = null;

        $user = Auth::user();
        abort_if(! $user || $user->hasRole(Role::SystemAdmin), 403);
        abort_unless($user->hasPermission('finance.expense.record'), 403);

        $this->validate([
            'category' => ['required', 'string', 'in:'.implode(',', Expense::CATEGORIES)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'date' => ['required', 'date'],
            'description' => ['required', 'string', 'min:2', 'max:1000'],
            'proofFile' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ]);

        $proofPath = null;
        if ($this->proofFile) {
            $proofPath = $this->proofFile->store('expenses/proofs', 'private');
        }

        $minorAmount = (int) round(((float) $this->amount) * 100);

        try {
            $expense = $action->execute($user, [
                'category' => $this->category,
                'amount' => $minorAmount,
                'date' => $this->date,
                'description' => $this->description,
                'proof_path' => $proofPath,
            ]);

            $this->successMessage = $expense->isPendingApproval()
                ? __('expense.expense_pending_approval')
                : __('expense.expense_recorded');

            $this->closeCreateModal();
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function confirmReverse(RecordExpense $action): void
    {
        $this->errorMessage = null;
        $this->successMessage = null;

        $user = Auth::user();
        abort_if(! $user || $user->hasRole(Role::SystemAdmin), 403);
        abort_unless($user->hasPermission('finance.expense.record'), 403);

        $this->validate([
            'reversalReason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        try {
            /** @var Expense $expense */
            $expense = Expense::findOrFail($this->reversingExpenseId);
            $action->reverse($user, $expense, $this->reversalReason);

            $this->successMessage = __('expense.expense_reversed');
            $this->closeReverseModal();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->categoryFilter = '';
        $this->statusFilter = '';
        $this->fromDate = '';
        $this->toDate = '';
        $this->resetPage();
    }

    private function resetCreateForm(): void
    {
        $this->category = Expense::CATEGORY_RENT;
        $this->amount = '';
        $this->date = now()->toDateString();
        $this->description = '';
        $this->proofFile = null;
        $this->errorMessage = null;
    }

    public function render(): View
    {
        $query = Expense::with(['creator', 'approver', 'reversals', 'reversedExpense']);

        if ($this->categoryFilter !== '') {
            $query->where('category', $this->categoryFilter);
        }

        if ($this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->fromDate !== '') {
            $query->where('date', '>=', $this->fromDate);
        }

        if ($this->toDate !== '') {
            $query->where('date', '<=', $this->toDate);
        }

        if (trim($this->search) !== '') {
            $term = '%'.trim($this->search).'%';
            $query->where(function ($q) use ($term) {
                $q->where('description', 'like', $term)
                    ->orWhere('reversal_reason', 'like', $term)
                    ->orWhereHas('creator', function ($uq) use ($term) {
                        $uq->where('name', 'like', $term);
                    });
            });
        }

        $expenses = $query->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(15);

        return view('livewire.finance.expenses.index', [
            'expenses' => $expenses,
            'categories' => Expense::CATEGORIES,
            'statuses' => Expense::STATUSES,
        ]);
    }
}
