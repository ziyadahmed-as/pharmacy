<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Shift;
use App\Models\ShiftCashMovement;

/**
 * Computes expected cash and tenders by payment method for a shift.
 *
 * Factors in:
 *  - opening float (cash)
 *  - completed sales tenders (per method; excludes voided sales)
 *  - cash in and cash out movements (cash only)
 *  - customer refunds (when present in UC-16)
 *
 * @uc UC-17 @fr FR-3.9 @ac AC-24
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

        // 2. Compute cash specifics
        $cashIn = (int) ShiftCashMovement::where('shift_id', $shift->id)
            ->where('type', ShiftCashMovement::TYPE_CASH_IN)
            ->sum('amount');

        $cashOut = (int) ShiftCashMovement::where('shift_id', $shift->id)
            ->where('type', ShiftCashMovement::TYPE_CASH_OUT)
            ->sum('amount');

        $cashFromSales = $salesPayments['cash'] ?? 0;
        $expectedCash = $shift->opening_float + $cashFromSales + $cashIn - $cashOut;

        // 3. Assemble full map ensuring 'cash' is always present
        $expected = ['cash' => max(0, $expectedCash)];

        // Standard payment methods to include
        $standardMethods = ['telebirr', 'cbe_birr', 'card', 'bank_transfer', 'mobile_money'];
        foreach ($standardMethods as $method) {
            if (isset($salesPayments[$method]) && $salesPayments[$method] > 0) {
                $expected[$method] = $salesPayments[$method];
            }
        }

        // Include any other non-standard payment methods that appeared in sales
        foreach ($salesPayments as $method => $amount) {
            if (! isset($expected[$method])) {
                $expected[$method] = $amount;
            }
        }

        return $expected;
    }
}

