<?php

namespace App\Livewire\Inventory\Disposals;

use App\Actions\Inventory\RequestDisposal;
use App\Enums\Role;
use App\Models\Disposal;
use App\Models\QuarantineRecord;
use App\Models\User;
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
#[Title('Stock Disposals')]
class Index extends Component
{
    use WithFileUploads, WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $statusFilter = '';

    #[Url]
    public string $methodFilter = '';

    // Create Modal state
    public bool $showCreateModal = false;

    public ?int $quarantine_record_id = null;

    public int $quantity = 1;

    public string $reason = 'expired';

    public string $method = 'return_to_supplier';

    public string $supplier_name = '';

    public string $collector_name = '';

    public ?int $performer_id = null;

    public ?int $witness_id = null;

    public string $notes = '';

    public $evidenceFile = null;

    public ?string $errorMessage = null;

    public ?string $successMessage = null;

    public function mount(): void
    {
        $user = Auth::user();
        abort_if(! $user || $user->hasRole(Role::SystemAdmin), 403);
        abort_unless($user->hasPermission('stock.dispose'), 403);

        $this->performer_id = $user->id;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingMethodFilter(): void
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

    public function updatedQuarantineRecordId(?int $val): void
    {
        if (! $val) {
            return;
        }

        $qr = QuarantineRecord::with('batch')->find($val);
        if ($qr) {
            $this->quantity = min($this->quantity ?: 1, max(1, $qr->disposableQuantity()));
            $this->supplier_name = (string) ($qr->batch?->supplier_name ?? '');
            if (in_array($qr->reason, Disposal::REASONS, true)) {
                $this->reason = $qr->reason;
            }
        }
    }

    public function resetCreateForm(): void
    {
        $user = Auth::user();
        $this->quarantine_record_id = null;
        $this->quantity = 1;
        $this->reason = Disposal::REASON_EXPIRED;
        $this->method = Disposal::METHOD_RETURN_TO_SUPPLIER;
        $this->supplier_name = '';
        $this->collector_name = '';
        $this->performer_id = $user ? $user->id : null;
        $this->witness_id = null;
        $this->notes = '';
        $this->evidenceFile = null;
        $this->errorMessage = null;
        $this->resetErrorBag();
    }

    public function submitDisposal(RequestDisposal $action)
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $this->errorMessage = null;

        $evidencePath = null;
        if ($this->evidenceFile) {
            $evidencePath = $this->evidenceFile->store('disposal_evidence', 'private');
        }

        try {
            $disposal = $action->execute($user, [
                'quarantine_record_id' => (int) $this->quarantine_record_id,
                'quantity' => (int) $this->quantity,
                'reason' => $this->reason,
                'method' => $this->method,
                'supplier_name' => $this->supplier_name,
                'collector_name' => $this->collector_name,
                'performer_id' => $this->performer_id,
                'witness_id' => $this->witness_id,
                'notes' => $this->notes,
                'evidence_path' => $evidencePath,
            ]);

            $this->showCreateModal = false;
            $this->resetCreateForm();
            session()->flash('success', __('disposal.requested_successfully'));

            return $this->redirect(route('inventory.disposals.show', $disposal), navigate: true);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->getMessageBag());
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render(): View
    {
        $user = Auth::user();

        $query = Disposal::with([
            'batch.product',
            'quarantineRecord',
            'requestedBy',
            'performer',
            'witness',
            'approvedBy',
            'approvalRequest',
        ]);

        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $query->where(function ($q) use ($term) {
                $q->where('certificate_no', 'like', $term)
                    ->orWhere('supplier_name', 'like', $term)
                    ->orWhere('collector_name', 'like', $term)
                    ->orWhereHas('batch', function ($bq) use ($term) {
                        $bq->where('batch_no', 'like', $term)
                            ->orWhereHas('product', fn ($pq) => $pq->where('brand', 'like', $term)->orWhere('generic_name', 'like', $term));
                    });
            });
        }

        if ($this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->methodFilter !== '') {
            $query->where('method', $this->methodFilter);
        }

        $disposals = $query->latest()->paginate(15);

        // Open quarantine records that have disposable quantity
        $openQuarantineRecords = QuarantineRecord::with(['batch.product'])
            ->where('status', QuarantineRecord::STATUS_OPEN)
            ->latest()
            ->get()
            ->filter(fn ($qr) => $qr->disposableQuantity() > 0);

        // Staff members eligible to perform or witness
        $eligibleStaff = User::where('is_active', true)
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', Role::SystemAdmin->value))
            ->orderBy('name')
            ->get();

        $isControlledSelected = false;
        if ($this->quarantine_record_id) {
            $selectedQr = $openQuarantineRecords->firstWhere('id', $this->quarantine_record_id);
            if ($selectedQr && $selectedQr->batch->product->classification === 'controlled') {
                $isControlledSelected = true;
            }
        }

        return view('livewire.inventory.disposals.index', [
            'disposals' => $disposals,
            'openQuarantineRecords' => $openQuarantineRecords,
            'eligibleStaff' => $eligibleStaff,
            'isControlledSelected' => $isControlledSelected,
            'user' => $user,
        ]);
    }
}
