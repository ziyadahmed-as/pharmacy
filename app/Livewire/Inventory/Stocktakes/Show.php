<?php

namespace App\Livewire\Inventory\Stocktakes;

use App\Actions\Inventory\ApproveStocktake;
use App\Actions\Inventory\RecordStocktakeCount;
use App\Enums\Role;
use App\Models\Stocktake;
use App\Models\StocktakeLine;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Stocktake')]
class Show extends Component
{
    public Stocktake $stocktake;

    // Count entry
    public ?int $selectedLineId = null;

    public string $countedQty = '';

    public ?int $secondPersonId = null;

    // Recount entry
    public ?int $recountLineId = null;

    public string $recountQty = '';

    // Approval / rejection
    public bool $showRejectModal = false;

    public string $rejectionReason = '';

    public ?string $errorMessage = null;

    public ?string $successMessage = null;

    public function mount(Stocktake $stocktake): void
    {
        $user = Auth::user();
        abort_if(! $user || $user->hasRole(Role::SystemAdmin), 403);
        abort_unless($user->hasPermission('stock.stocktake'), 403);

        $this->stocktake = $stocktake;
    }

    // ── Count entry ──────────────────────────────────────────────────────────

    public function selectLine(int $lineId): void
    {
        $this->selectedLineId = $lineId;
        $this->countedQty = '';
        $this->secondPersonId = null;
        $this->errorMessage = null;
    }

    public function saveCount(RecordStocktakeCount $action): void
    {
        $this->errorMessage = null;
        $user = Auth::user();
        if (! $user || ! $this->selectedLineId) {
            return;
        }

        try {
            $action->execute($user, [
                'line_id' => $this->selectedLineId,
                'counted_qty' => (int) $this->countedQty,
                'second_person_id' => $this->secondPersonId,
            ]);

            $this->selectedLineId = null;
            $this->countedQty = '';
            $this->secondPersonId = null;
            $this->stocktake->refresh();
            $this->successMessage = __('stocktake.count_saved');
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->getMessageBag());
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    // ── Recount entry ────────────────────────────────────────────────────────

    public function openRecountEntry(int $lineId): void
    {
        $this->recountLineId = $lineId;
        $this->recountQty = '';
        $this->errorMessage = null;
    }

    public function saveRecount(RecordStocktakeCount $action): void
    {
        $this->errorMessage = null;
        $user = Auth::user();
        if (! $user || ! $this->recountLineId) {
            return;
        }

        try {
            $line = StocktakeLine::findOrFail($this->recountLineId);
            $action->recordRecount($user, $line, (int) $this->recountQty);

            $this->recountLineId = null;
            $this->recountQty = '';
            $this->stocktake->refresh();
            $this->successMessage = __('stocktake.recount_saved');
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->getMessageBag());
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    // ── Submit for review ────────────────────────────────────────────────────

    public function submitForReview(ApproveStocktake $action): void
    {
        $this->errorMessage = null;
        $user = Auth::user();
        if (! $user) {
            return;
        }

        try {
            $action->submitForReview($user, $this->stocktake);
            $this->stocktake->refresh();
            $this->successMessage = __('stocktake.submitted_for_review');
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    // ── Approve ──────────────────────────────────────────────────────────────

    public function approve(ApproveStocktake $action): void
    {
        $this->errorMessage = null;
        $user = Auth::user();
        if (! $user) {
            return;
        }

        try {
            $action->approve($user, $this->stocktake);
            $this->stocktake->refresh();
            $this->successMessage = __('stocktake.approved_successfully');
        } catch (ValidationException $e) {
            $this->errorMessage = collect($e->errors())->flatten()->implode(', ');
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    // ── Reject ───────────────────────────────────────────────────────────────

    public function openRejectModal(): void
    {
        $this->rejectionReason = '';
        $this->showRejectModal = true;
    }

    public function closeRejectModal(): void
    {
        $this->showRejectModal = false;
    }

    public function reject(ApproveStocktake $action): void
    {
        $this->errorMessage = null;
        $user = Auth::user();
        if (! $user) {
            return;
        }

        try {
            $action->reject($user, $this->stocktake, $this->rejectionReason);
            $this->showRejectModal = false;
            $this->stocktake->refresh();
            $this->successMessage = __('stocktake.rejected_successfully');
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->getMessageBag());
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    // ── Computed helpers ─────────────────────────────────────────────────────

    public function getWitnessUsersProperty(): Collection
    {
        $user = Auth::user();

        return User::query()
            ->where('is_active', true)
            ->where('id', '!=', $user?->id)
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', Role::SystemAdmin->value))
            ->orderBy('name')
            ->get();
    }

    public function getCanApproveProperty(): bool
    {
        $user = Auth::user();
        if (! $user || $this->stocktake->status !== Stocktake::STATUS_REVIEW) {
            return false;
        }

        $creator = $this->stocktake->createdBy;
        if ($creator && $creator->hasRole(\App\Enums\Role::PharmacyManager)) {
            return $user->hasRole(\App\Enums\Role::Owner);
        }

        return $user->hasAnyRole(['pharmacy_manager', 'owner'])
            && $this->stocktake->created_by !== $user->id;
    }

    public function render(): View
    {
        $user = Auth::user();
        $canSeeCost = $user?->hasAnyRole(['pharmacy_manager', 'owner']);

        $lines = $this->stocktake->lines()
            ->with(['batch.product', 'countedBy', 'secondPerson'])
            ->orderBy('id')
            ->get();

        return view('livewire.inventory.stocktakes.show', [
            'lines' => $lines,
            'canSeeCost' => $canSeeCost,
        ]);
    }
}

