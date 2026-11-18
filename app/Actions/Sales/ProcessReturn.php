<?php

namespace App\Actions\Sales;

use App\Models\ApprovalRequest;
use App\Models\CreditAccount;
use App\Models\CreditPayment;
use App\Models\DailyClosing;
use App\Models\PaymentMethod;
use App\Models\QuarantineRecord;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\SaleReturnLine;
use App\Models\Shift;
use App\Models\User;
use App\Services\ApprovalService;
use App\Services\AuditLogger;
use App\Services\CurrentShift;
use App\Services\DocumentSequence;
use App\Services\StockMovementService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Process a customer return and refund against a completed sale.
 *
 * @uc UC-16 @fr FR-3.8 @br BR-18 @ac AC-15
 */
class ProcessReturn
{
    public function __construct(private readonly ApprovalService $approvals) {}

    /**
     * @param  array<int, array{sale_line_id: int, quantity: int, assessment: string, reason?: string|null}>  $items
     * @return array{sale_return: SaleReturn, requires_approval: bool, approval_request: ?ApprovalRequest}
     *
     * @throws AuthorizationException
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function execute(
        User $user,
        string $receiptNo,
        array $items,
        ?string $generalReason = null,
        ?Shift $shift = null
    ): array {
        // 1. Authorisation & Open Shift requirement
        if (! $user->hasPermission('shifts.open')) {
            throw new AuthorizationException(__('sales.unauthorized_to_process_return'));
        }

        $activeShift = $shift ?? CurrentShift::requireForUser($user);

        // 2. Find sale by receipt number (E1)
        if (empty(trim($receiptNo))) {
            throw new InvalidArgumentException(__('sales.receipt_number_required'));
        }

        /** @var Sale $sale */
        $sale = Sale::with(['lines.batch', 'payments'])->where('receipt_no', trim($receiptNo))->first();

        if (! $sale) {
            throw new RuntimeException(__('sales.sale_not_found'));
        }

        if ($sale->isVoided()) {
            throw new RuntimeException(__('sales.cannot_refund_voided_sale'));
        }

        if (! $sale->isCompleted()) {
            throw new RuntimeException(__('sales.cannot_refund_uncompleted_sale'));
        }

        // Check if the sale date is locked (E2)
        if ($sale->completed_at && DailyClosing::isLockedForDate($sale->completed_at)) {
            throw new RuntimeException(__('sales.refund_error_locked_day'));
        }

        if (DailyClosing::isLockedForDate(business_date())) {
            throw new RuntimeException(__('closing.day_is_locked_error'));
        }

        // 3. Check Return Window (A1)
        $windowDays = (int) setting('sales.return_window_days', 14);
        if ($windowDays > 0 && $sale->completed_at) {
            $daysSinceSale = abs((int) Carbon::now()->diffInDays($sale->completed_at));
            if ($daysSinceSale > $windowDays) {
                throw new RuntimeException(__('sales.outside_return_window_error', ['days' => $windowDays]));
            }
        }

        if (empty($items)) {
            throw new InvalidArgumentException(__('sales.return_items_required'));
        }

        // 4. Validate lines & quantities (E2)
        $saleLinesById = $sale->lines->keyBy('id');
        $validatedLines = [];
        $totalRefund = 0;

        foreach ($items as $item) {
            $lineId = $item['sale_line_id'] ?? null;
            $qty = (int) ($item['quantity'] ?? 0);
            $assessment = $item['assessment'] ?? SaleReturnLine::ASSESSMENT_RESALEABLE;
            $reason = $item['reason'] ?? $generalReason;

            if (! isset($saleLinesById[$lineId])) {
                throw new InvalidArgumentException(__('sales.invalid_sale_line'));
            }

            $saleLine = $saleLinesById[$lineId];
            $remainingQty = $saleLine->remainingReturnableQuantity();

            if ($qty <= 0) {
                throw new InvalidArgumentException(__('sales.return_quantity_must_be_positive'));
            }

            if ($qty > $remainingQty) {
                throw new RuntimeException(__('sales.quantity_exceeds_sold_error', [
                    'item' => $saleLine->product?->brand ?? 'Item',
                    'available' => $remainingQty,
                    'requested' => $qty,
                ]));
            }

            if (! in_array($assessment, [SaleReturnLine::ASSESSMENT_RESALEABLE, SaleReturnLine::ASSESSMENT_NOT_RESALEABLE], true)) {
                throw new InvalidArgumentException(__('sales.invalid_assessment_type'));
            }

            $lineSubtotal = $saleLine->unit_price * $qty;
            $totalRefund += $lineSubtotal;

            $validatedLines[] = [
                'sale_line' => $saleLine,
                'batch_id' => $saleLine->batch_id,
                'quantity' => $qty,
                'unit_price' => $saleLine->unit_price,
                'subtotal' => $lineSubtotal,
                'assessment' => $assessment,
                'reason' => $reason,
            ];
        }

        // 5. Payment method: default to original tender
        $primaryPaymentMethod = $sale->payments->first()?->method ?? 'cash';

        // 6. Check Refund Limit (Step 4)
        $limitSetting = setting('sales.refund_limit');
        // If limit is null or 'Not set', or if totalRefund exceeds limit -> requires PM approval
        $requiresApproval = false;
        if ($limitSetting === null || $limitSetting === '' || $limitSetting === 'Not set') {
            $requiresApproval = true;
        } else {
            $limitSantim = (int) $limitSetting;
            if ($totalRefund > $limitSantim) {
                $requiresApproval = true;
            }
        }

        return DB::transaction(function () use ($user, $sale, $activeShift, $validatedLines, $totalRefund, $primaryPaymentMethod, $generalReason, $requiresApproval) {
            $lockedShift = Shift::where('id', $activeShift->id)->lockForUpdate()->first();
            if (! $lockedShift || ! $lockedShift->isOpen()) {
                throw new RuntimeException(__('shifts.no_open_shift_error'));
            }

            if (DailyClosing::isLockedForDate(business_date())) {
                throw new RuntimeException(__('closing.day_is_locked_error'));
            }

            if ($sale->completed_at && DailyClosing::isLockedForDate($sale->completed_at)) {
                throw new RuntimeException(__('sales.refund_error_locked_day'));
            }

            $refundNo = DocumentSequence::next('refund', 'RFD');

            $status = $requiresApproval ? SaleReturn::STATUS_PENDING_APPROVAL : SaleReturn::STATUS_COMPLETED;
            $pmModel = PaymentMethod::where('code', $primaryPaymentMethod)->first();

            $saleReturn = SaleReturn::create([
                'refund_no' => $refundNo,
                'sale_id' => $sale->id,
                'user_id' => $user->id,
                'shift_id' => $activeShift->id,
                'status' => $status,
                'total_refund' => $totalRefund,
                'payment_method_id' => $pmModel?->id,
                'payment_method' => $primaryPaymentMethod,
                'reason' => $generalReason,
                'requires_approval' => $requiresApproval,
                'completed_at' => $requiresApproval ? null : now(),
            ]);

            foreach ($validatedLines as $lineData) {
                $saleReturn->lines()->create([
                    'sale_line_id' => $lineData['sale_line']->id,
                    'batch_id' => $lineData['batch_id'],
                    'quantity' => $lineData['quantity'],
                    'unit_price' => $lineData['unit_price'],
                    'subtotal' => $lineData['subtotal'],
                    'assessment' => $lineData['assessment'],
                    'reason' => $lineData['reason'],
                ]);
            }

            $approvalRequest = null;

            if ($requiresApproval) {
                // Creates approval request via UC-34 engine
                $approvalRequest = $this->approvals->request(
                    type: 'sale.refund',
                    initiator: $user,
                    payload: [
                        'sale_return_id' => $saleReturn->id,
                        'refund_no' => $saleReturn->refund_no,
                        'total_refund' => $totalRefund,
                        'sale_id' => $sale->id,
                    ],
                    summary: "Customer refund {$refundNo} for sale {$sale->receipt_no} (".number_format($totalRefund / 100, 2).' ETB)',
                    subject: $saleReturn,
                    comment: $generalReason
                );
            } else {
                // Complete immediately: move stock
                foreach ($validatedLines as $lineData) {
                    $batch = $lineData['sale_line']->batch;

                    // Never add resaleable stock back to an expired batch.
                    // If the batch has expired since the sale, send to quarantine instead.
                    $isExpired = $batch->expiry_date && $batch->expiry_date->isPast();

                    if ($lineData['assessment'] === SaleReturnLine::ASSESSMENT_RESALEABLE && ! $isExpired) {
                        StockMovementService::post(
                            batch: $batch,
                            signedQuantity: $lineData['quantity'],
                            type: 'return',
                            source: $saleReturn,
                            user: $user,
                            status: 'sellable',
                            description: "Customer return (resaleable): {$refundNo}"
                        );
                    } else {
                        // Not resaleable OR batch expired → quarantine
                        $quarantineReason = $isExpired
                            ? QuarantineRecord::REASON_EXPIRED
                            : QuarantineRecord::REASON_DAMAGED;

                        $quarantine = QuarantineRecord::create([
                            'batch_id' => $batch->id,
                            'quantity' => $lineData['quantity'],
                            'reason' => $quarantineReason,
                            'status' => QuarantineRecord::STATUS_OPEN,
                            'created_by' => $user->id,
                            'note' => $isExpired
                                ? "Customer return to expired batch {$batch->batch_no} ({$refundNo}): redirected to quarantine"
                                : "Customer return assessed damaged/not resaleable ({$refundNo}): ".($lineData['reason'] ?? 'Customer return'),
                        ]);

                        StockMovementService::post(
                            batch: $batch,
                            signedQuantity: $lineData['quantity'],
                            type: 'quarantine',
                            source: $quarantine,
                            user: $user,
                            status: 'quarantine',
                            description: $isExpired
                                ? "Customer return to expired batch (quarantine): {$refundNo}"
                                : "Customer return (quarantine): {$refundNo}"
                        );
                    }
                }

                // If sale was on credit or customer has credit account, reduce credit balance (UC-26)
                if ($sale->customer_id) {
                    $creditAccount = CreditAccount::where('customer_id', $sale->customer_id)->first();
                    if ($creditAccount && ($primaryPaymentMethod === 'credit' || $sale->payments()->where('method', 'credit')->exists())) {
                        $refundCreditAmount = min($totalRefund, $creditAccount->balance);
                        if ($refundCreditAmount > 0) {
                            $creditAccount->decrement('balance', $refundCreditAmount);
                            CreditPayment::create([
                                'credit_account_id' => $creditAccount->id,
                                'sale_id' => $sale->id,
                                'amount' => $refundCreditAmount,
                                'direction' => 'payment',
                                'method' => 'credit',
                                'receipt_no' => $refundNo,
                                'reference' => $refundNo,
                                'user_id' => $user->id,
                                'notes' => "Refund {$refundNo} for sale {$sale->receipt_no}",
                            ]);
                        }
                    }
                }

                AuditLogger::record(
                    action: 'sale.refund.completed',
                    subject: $saleReturn,
                    old: [],
                    new: [
                        'refund_no' => $refundNo,
                        'total_refund' => $totalRefund,
                        'payment_method' => $primaryPaymentMethod,
                        'status' => 'completed',
                    ],
                    user: $user,
                    description: "Refund {$refundNo} completed by {$user->username}"
                );
            }

            return [
                'sale_return' => $saleReturn,
                'requires_approval' => $requiresApproval,
                'approval_request' => $approvalRequest,
            ];
        });
    }
}
