<?php

namespace App\Livewire\Inventory\Adjustments;

use App\Actions\Inventory\RecordRecount;
use App\Enums\Role;
use App\Models\AdjustmentRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Stock Adjustments')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $statusFilter = '';

    #[Url]
    public string $reasonFilter = '';

    // Recount modal state
    public bool $showRecountModal = false;

    public ?int $selectedAdjustmentId = null;

    public string $recountNotes = '';

    public ?string $errorMessage = null;

    public ?string $successMessage = null;

    public function mount(): void
    {
        $user = Auth::user();
        abort_if(! $user || $user->hasRole(Role::SystemAdmin), 403);
        abort_unless($user->hasPermission('stock.adjust.request'), 403);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingReasonFilter(): void
    {
        $this->resetPage();
    }

    public function openRecountModal(int $id): void
    {
        $this->errorMessage = null;
        $this->selectedAdjustmentId = $id;
        $this->recountNotes = '';
        $this->showRecountModal = true;
    }

    public function closeRecountModal(): void
    {
        $this->showRecountModal = false;
        $this->selectedAdjustmentId = null;
        $this->recountNotes = '';
        $this->errorMessage = null;
    }

    public function submitRecount(RecordRecount $action): void
    {
        $this->errorMessage = null;

        if (! $this->selectedAdjustmentId) {
            return;
        }

        try {
            /** @var AdjustmentRequest $adj */
            $adj = AdjustmentRequest::findOrFail($this->selectedAdjustmentId);
            $action->execute(Auth::user(), $adj, $this->recountNotes);

            $this->showRecountModal = false;
            $this->selectedAdjustmentId = null;
            $this->recountNotes = '';
            $this->successMessage = __('catalogue.recount_recorded_success');
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render(): View
    {
        $query = AdjustmentRequest::query()
            ->with(['batch.product', 'requester', 'secondPerson', 'recounter', 'approvalRequest'])
            ->latest();

        if ($this->search !== '') {
            $term = '%'.trim($this->search).'%';
            $query->where(function ($q) use ($term) {
                $q->whereHas('batch', function ($bq) use ($term) {
                    $bq->where('batch_no', 'like', $term)
                        ->orWhereHas('product', function ($pq) use ($term) {
                            $pq->where('brand', 'like', $term)
                                ->orWhere('generic_name', 'like', $term);
                        });
                })->orWhere('written_reason', 'like', $term);
            });
        }

        if ($this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->reasonFilter !== '') {
            $query->where('reason_type', $this->reasonFilter);
        }

        $adjustments = $query->paginate(15);

        return view('livewire.inventory.adjustments.index', [
            'adjustments' => $adjustments,
            'reasonOptions' => [
                AdjustmentRequest::REASON_DAMAGE => __('catalogue.adjustment_reason_damage'),
                AdjustmentRequest::REASON_LOSS => __('catalogue.adjustment_reason_loss'),
                AdjustmentRequest::REASON_EXPIRY_WRITE_OFF => __('catalogue.adjustment_reason_expiry_write_off'),
                AdjustmentRequest::REASON_FOUND_STOCK => __('catalogue.adjustment_reason_found_stock'),
            ],
            'statusOptions' => [
                AdjustmentRequest::STATUS_PENDING_APPROVAL => __('common.status_pending'),
                AdjustmentRequest::STATUS_APPROVED => __('approvals.status.approved'),
                AdjustmentRequest::STATUS_REJECTED => __('approvals.status.rejected'),
                AdjustmentRequest::STATUS_EXPIRED => __('approvals.status.expired'),
            ],
            'pendingCount' => AdjustmentRequest::where('status', AdjustmentRequest::STATUS_PENDING_APPROVAL)->count(),
        ]);
    }
}
