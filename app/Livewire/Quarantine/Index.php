<?php

namespace App\Livewire\Quarantine;

use App\Actions\Inventory\QuarantineStock;
use App\Actions\Inventory\RequestQuarantineRelease;
use App\Models\Batch;
use App\Models\BatchStock;
use App\Models\QuarantineRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Livewire component for managing quarantine records and requests (UC-08 Step 6).
 *
 * @uc UC-08 @fr FR-2.15 @br BR-01, BR-10 @ac AC-07
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = 'open'; // 'all', 'open', 'released', 'disposed', 'returned'

    #[Url]
    public string $reason = ''; // '', 'expired', 'damaged', 'recalled', 'contaminated', 'other'

    // Quarantine Modal state
    public bool $showQuarantineModal = false;

    public ?int $selectedBatchId = null;

    public ?int $quarantineQuantity = null;

    public string $quarantineReason = QuarantineRecord::REASON_DAMAGED;

    public string $quarantineNote = '';

    public int $availableSellableQty = 0;

    // Release Modal state
    public bool $showReleaseModal = false;

    public ?int $releaseRecordId = null;

    public string $releaseReason = '';

    public ?QuarantineRecord $selectedReleaseRecord = null;

    public function mount(): void
    {
        $this->authorizeAccess();
    }

    private function authorizeAccess(): void
    {
        $user = Auth::user();
        abort_unless($user && $user->canAccessBusinessData() && $user->hasPermission('stock.view'), 403);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingReason(): void
    {
        $this->resetPage();
    }

    public function openQuarantineModal(?int $batchId = null): void
    {
        $this->resetValidation();
        $this->selectedBatchId = $batchId;
        $this->quarantineQuantity = null;
        $this->quarantineReason = QuarantineRecord::REASON_DAMAGED;
        $this->quarantineNote = '';
        $this->updateAvailableSellable();
        $this->showQuarantineModal = true;
    }

    public function updatedSelectedBatchId(): void
    {
        $this->updateAvailableSellable();
    }

    private function updateAvailableSellable(): void
    {
        if ($this->selectedBatchId) {
            $this->availableSellableQty = (int) BatchStock::where('batch_id', $this->selectedBatchId)
                ->where('status', 'sellable')
                ->sum('quantity');
        } else {
            $this->availableSellableQty = 0;
        }
    }

    public function submitQuarantine(QuarantineStock $action): void
    {
        $this->authorizeAccess();

        $this->validate([
            'selectedBatchId' => 'required|exists:batches,id',
            'quarantineQuantity' => 'required|integer|min:1|max:'.max(1, $this->availableSellableQty),
            'quarantineReason' => 'required|in:'.implode(',', QuarantineRecord::REASONS),
            'quarantineNote' => 'nullable|string|max:1000',
        ]);

        $batch = Batch::findOrFail($this->selectedBatchId);

        try {
            $action->execute(
                batch: $batch,
                quantity: (int) $this->quarantineQuantity,
                reason: $this->quarantineReason,
                note: $this->quarantineNote ?: null,
                actor: Auth::user()
            );

            $this->showQuarantineModal = false;
            session()->flash('status', __('catalogue.quarantine_success'));
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->getMessageBag());
        }
    }

    public function openReleaseModal(int $recordId): void
    {
        $this->resetValidation();
        $this->releaseRecordId = $recordId;
        $this->releaseReason = '';
        $this->selectedReleaseRecord = QuarantineRecord::with('batch.product')->findOrFail($recordId);

        if ($this->selectedReleaseRecord->isRecalled()) {
            session()->flash('error', __('catalogue.recalled_cannot_be_released'));

            return;
        }

        $this->showReleaseModal = true;
    }

    public function submitRelease(RequestQuarantineRelease $action): void
    {
        $this->authorizeAccess();

        $this->validate([
            'releaseReason' => 'required|string|min:3|max:500',
        ]);

        $record = QuarantineRecord::with('batch.product')->findOrFail($this->releaseRecordId);

        try {
            $action->execute(
                record: $record,
                reason: $this->releaseReason,
                actor: Auth::user()
            );

            $this->showReleaseModal = false;
            session()->flash('status', __('catalogue.quarantine_release_requested'));
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->getMessageBag());
        }
    }

    public function render()
    {
        $query = QuarantineRecord::query()
            ->with(['batch.product', 'creator'])
            ->latest('id');

        if ($this->status !== 'all' && in_array($this->status, QuarantineRecord::STATUSES, true)) {
            $query->where('status', $this->status);
        }

        if ($this->reason !== '' && in_array($this->reason, QuarantineRecord::REASONS, true)) {
            $query->where('reason', $this->reason);
        }

        if (trim($this->search) !== '') {
            $term = trim($this->search);
            $query->where(function ($q) use ($term) {
                $q->whereHas('batch.product', function ($pQ) use ($term) {
                    $pQ->where('brand', 'like', "%{$term}%")
                        ->orWhere('generic_name', 'like', "%{$term}%");
                })->orWhereHas('batch', function ($bQ) use ($term) {
                    $bQ->where('batch_no', 'like', "%{$term}%");
                });
            });
        }

        $records = $query->paginate(15);

        // Aggregate stats
        $totalQuarantinedQty = (int) BatchStock::where('status', 'quarantine')->sum('quantity');
        $openRecordsCount = QuarantineRecord::where('status', QuarantineRecord::STATUS_OPEN)->count();
        $recalledCount = QuarantineRecord::where('status', QuarantineRecord::STATUS_OPEN)
            ->where('reason', QuarantineRecord::REASON_RECALLED)
            ->count();

        // Available batches for quarantine modal dropdown
        $sellableBatches = Batch::query()
            ->whereHas('stocks', fn ($q) => $q->where('status', 'sellable')->where('quantity', '>', 0))
            ->with(['product', 'stocks' => fn ($q) => $q->where('status', 'sellable')])
            ->orderBy('product_id')
            ->get();

        return view('livewire.quarantine.index', [
            'records' => $records,
            'totalQuarantinedQty' => $totalQuarantinedQty,
            'openRecordsCount' => $openRecordsCount,
            'recalledCount' => $recalledCount,
            'sellableBatches' => $sellableBatches,
        ]);
    }
}
