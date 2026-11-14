<?php

namespace App\Livewire\Inventory\Stocktakes;

use App\Actions\Inventory\CreateStocktake;
use App\Enums\Role;
use App\Models\Stocktake;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Stocktakes')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $statusFilter = '';

    public string $type = Stocktake::TYPE_FULL;

    public string $scope_type = 'category';

    public string $scope_value = '';

    public bool $showCreateModal = false;

    public ?string $errorMessage = null;

    public ?string $successMessage = null;

    public function mount(): void
    {
        $user = Auth::user();
        abort_if(! $user || $user->hasRole(Role::SystemAdmin), 403);
        abort_unless($user->hasPermission('stock.stocktake'), 403);
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->reset(['type', 'scope_type', 'scope_value', 'errorMessage']);
        $this->type = Stocktake::TYPE_FULL;
        $this->scope_type = 'category';
        $this->showCreateModal = true;
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
    }

    public function createStocktake(CreateStocktake $action)
    {
        $this->errorMessage = null;
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        try {
            $stocktake = $action->execute($user, [
                'type' => $this->type,
                'scope_type' => $this->type === Stocktake::TYPE_PARTIAL ? $this->scope_type : null,
                'scope_value' => $this->type === Stocktake::TYPE_PARTIAL ? $this->scope_value : null,
            ]);

            $this->showCreateModal = false;
            session()->flash('success', __('stocktake.created_successfully'));

            return $this->redirect(route('inventory.stocktakes.show', $stocktake), navigate: true);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->getMessageBag());
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render(): View
    {
        $user = Auth::user();

        $query = Stocktake::with(['createdBy', 'approvedBy'])
            ->withCount('lines');

        if ($this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
        }

        $query->orderByDesc('id');

        return view('livewire.inventory.stocktakes.index', [
            'stocktakes' => $query->paginate(15),
            'canManage' => $user?->hasAnyRole(['pharmacy_manager', 'owner']),
        ]);
    }
}
