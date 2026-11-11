<?php

namespace App\Livewire\Sales;

use App\Actions\Sales\RequestVoid;
use App\Models\ApprovalRequest;
use App\Models\Sale;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Sale detail view with void-request flow.
 *
 * Shows:  sale lines, payments, totals, receipt number, status badge.
 * Action: "Request Void" (pharmacy_staff / PM) → modal with reason → ApprovalRequest.
 * Shows approval request status (pending / approved / rejected with comment).
 *
 * @uc UC-15 @fr FR-3.7 @br BR-05,BR-14 @ac AC-14
 */
class Show extends Component
{
    #[Locked]
    public int $saleId;

    public Sale $sale;

    // Void modal state
    public bool $showVoidModal = false;

    public string $voidReason = '';

    public string $voidError = '';

    public string $voidSuccess = '';

    public function mount(Sale $sale): void
    {
        Gate::authorize('shifts.open');
        $this->saleId = $sale->id;
        $this->sale = $sale->load(['lines.product', 'lines.batch', 'payments', 'shift.till', 'user']);
    }

    // ─── Void Request ─────────────────────────────────────────────────────────

    public function openVoidModal(): void
    {
        Gate::authorize('sales.void.request');
        $this->voidReason = '';
        $this->voidError = '';
        $this->voidSuccess = '';
        $this->showVoidModal = true;
    }

    public function submitVoidRequest(RequestVoid $action): void
    {
        Gate::authorize('sales.void.request');

        $this->voidError = '';
        $this->voidSuccess = '';

        $this->validate([
            'voidReason' => 'required|string|min:5|max:500',
        ], [
            'voidReason.required' => __('sales.void_error_no_reason'),
            'voidReason.min' => __('sales.void_reason_too_short'),
        ]);

        try {
            $action->execute(
                initiator: Auth::user(),
                receiptNo: $this->sale->receipt_no,
                reason: $this->voidReason,
            );

            $this->showVoidModal = false;
            $this->voidReason = '';
            $this->voidSuccess = __('sales.void_requested_successfully');

            // Reload to show pending approval badge.
            $this->sale = Sale::with(['lines.product', 'lines.batch', 'payments', 'shift.till', 'user'])->findOrFail($this->saleId);
        } catch (AuthorizationException $e) {
            $this->voidError = __('sales.void_unauthorized');
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            $this->voidError = $e->getMessage();
        }
    }

    public function closeVoidModal(): void
    {
        $this->showVoidModal = false;
        $this->voidError = '';
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Returns the most recent void request for this sale (pending or decided).
     */
    public function voidRequest(): ?ApprovalRequest
    {
        return ApprovalRequest::query()
            ->where('type', 'sale.void')
            ->where('subject_type', (new Sale)->getMorphClass())
            ->where('subject_id', $this->saleId)
            ->latest()
            ->first();
    }

    public function render()
    {
        $voidRequest = $this->voidRequest();

        return view('livewire.sales.show', [
            'sale' => $this->sale,
            'voidRequest' => $voidRequest,
            'canVoid' => Auth::user()->hasPermission('sales.void.request'),
        ])->layout('layouts.app');
    }
}
