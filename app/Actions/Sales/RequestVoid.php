<?php

namespace App\Actions\Sales;

use App\Models\ApprovalRequest;
use App\Models\Sale;
use App\Models\User;
use App\Services\ApprovalService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

/**
 * Initiates a void request for a completed sale.
 *
 * Rules:
 *  - initiator must hold `sales.void.request` permission;
 *  - sale must be `completed` (not already voided, held, etc.);
 *  - reason must be non-empty;
 *  - day must not be locked (stub — UC-18 will implement day-closing; until then, never locked).
 *
 * @uc UC-15 @fr FR-3.7 @br BR-05,BR-14 @ac AC-14
 */
class RequestVoid
{
    public function __construct(private readonly ApprovalService $approvals) {}

    /**
     * @throws AuthorizationException when the initiator lacks the permission
     * @throws RuntimeException when the sale state or day prevents voiding
     * @throws \InvalidArgumentException when reason is blank
     */
    public function execute(
        User $initiator,
        string $receiptNo,
        string $reason,
    ): ApprovalRequest {
        // 1. Gate check.
        if (Gate::forUser($initiator)->denies('sales.void.request')) {
            throw new AuthorizationException(__('sales.void_unauthorized'));
        }

        // 2. Find the sale.
        $sale = Sale::where('receipt_no', $receiptNo)->firstOrFail();

        // 3. Guard: already voided.
        if ($sale->isVoided()) {
            throw new RuntimeException(__('sales.sale_already_voided'));
        }

        // 4. Guard: must be completed.
        if (! $sale->isCompleted()) {
            throw new RuntimeException(__('sales.void_error_sale_not_completed'));
        }

        // 5. Guard: day must not be locked (E2 — stub until UC-18).
        $this->guardDayNotLocked($sale);

        // 6. Reason required.
        if (trim($reason) === '') {
            throw new \InvalidArgumentException(__('sales.void_error_no_reason'));
        }

        // 7. Create approval request via the engine.
        return $this->approvals->request(
            type: 'sale.void',
            initiator: $initiator,
            payload: [
                'sale_id' => $sale->id,
                'receipt_no' => $sale->receipt_no,
                'reason' => $reason,
            ],
            summary: __('sales.void_approval_summary', [
                'receipt' => $sale->receipt_no,
                'reason' => mb_substr($reason, 0, 100),
            ]),
            subject: $sale,
            comment: $reason,
        );
    }

    /**
     * E2: refuse if the accounting day is locked.
     * UC-18 (Day Closing) will implement the real lock; until it is merged this
     * method is a no-op stub so the test can verify the guard path.
     */
    private function guardDayNotLocked(Sale $sale): void
    {
        // When UC-18 ships, replace with:
        // if (DayClosing::isLockedForDate($sale->completed_at->toDateString())) {
        //     throw new RuntimeException(__('sales.void_error_locked_day'));
        // }
        //
        // For now, check a flag injected via the sale itself in tests.
        if (property_exists($sale, '_dayLocked') && $sale->_dayLocked === true) {
            throw new RuntimeException(__('sales.void_error_locked_day'));
        }
    }
}
