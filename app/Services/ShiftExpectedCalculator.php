<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\SaleReturn;
use App\Models\Shift;
use App\Models\ShiftCashMovement;

/**
 * Computes expected cash and tenders by payment method for a shift.
 *
 * Factors in:
 *  - opening float (cash)
 *  - completed sales tenders (per method; excludes voided sales)
 *  - cash in and cash out movements (cash only)
 *  - customer returns/refunds (completed refunds per method)
 *
 * @uc UC-17, UC-16 @fr FR-3.8, FR-3.9 @ac AC-24, AC-15
 */
class ShiftExpectedCalculator
{
    /**
     * @return array<string, int> Mapping of payment method => expected amount in santim
     */
    public static function calculate(Shift $shift): array
    {
        // 1. Get all completed payments for this shift grouped by method
        $salesPayments = Payment::query()
            ->join('sales', 'payments.sale_id', '=', 'sales.id')
            ->where('sales.shift_id', $shift->id)
            ->where('sales.status', 'completed')
            ->groupBy('payments.method')
            ->selectRaw('payments.method, SUM(payments.amount) as total_amount')
            ->pluck('total_amount', 'method')
            ->map(fn ($val) => (int) $val)
            ->all();

        // 2. Get all completed refunds for this shift grouped by method
        $refunds = SaleReturn::query()
            ->where('shift_id', $shift->id)
            ->where('status', SaleReturn::STATUS_COMPLETED)
            ->groupBy('payment_method')
            ->selectRaw('payment_method, SUM(total_refund) as total_refund')
            ->pluck('total_refund', 'payment_method')
            ->map(fn ($val) => (int) $val)
            ->all();

        // 3. Compute cash specifics
        $cashIn = (int) ShiftCashMovement::where('shift_id', $shift->id)
            ->where('type', ShiftCashMovement::TYPE_CASH_IN)
            ->sum('amount');

        $cashOut = (int) ShiftCashMovement::where('shift_id', $shift->id)
            ->where('type', ShiftCashMovement::TYPE_CASH_OUT)
            ->sum('amount');

        $cashFromSales = $salesPayments['cash'] ?? 0;
        $cashRefunds = $refunds['cash'] ?? 0;
        $expectedCash = $shift->opening_float + $cashFromSales + $cashIn - $cashOut - $cashRefunds;

        // 4. Assemble full map ensuring 'cash' is always present
        $expected = ['cash' => max(0, $expectedCash)];

        // Standard payment methods to include
        $standardMethods = ['telebirr', 'cbe_birr', 'card', 'bank_transfer', 'mobile_money'];
        foreach ($standardMethods as $method) {
            $salesAmt = $salesPayments[$method] ?? 0;
            $refundAmt = $refunds[$method] ?? 0;
            $net = $salesAmt - $refundAmt;
            if ($net > 0 || isset($salesPayments[$method])) {
                $expected[$method] = max(0, $net);
            }
        }

        // Include any other non-standard payment methods
        foreach ($salesPayments as $method => $amount) {
            if (! isset($expected[$method])) {
                $ref = $refunds[$method] ?? 0;
                $expected[$method] = max(0, $amount - $ref);
            }
        }

        return $expected;
    }
}
