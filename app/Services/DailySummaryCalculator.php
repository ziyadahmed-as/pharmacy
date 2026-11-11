<?php

namespace App\Services;

use App\Models\HeldSale;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Shift;
use Illuminate\Database\Eloquent\Collection;

/**
 * Aggregates daily shift figures, revenue, voids, refunds and held sales for daily closing.
 *
 * @uc UC-18 @fr FR-3.10, FR-6.1, FR-6.6 @ac AC-24
 */
class DailySummaryCalculator
{
    /**
     * @return array<string, int> Mapping of payment method => system total amount in santim
     */
    public static function calculateSystemTotals(string $date): array
    {
        $shifts = Shift::whereDate('opened_at', $date)
            ->where('status', 'closed')
            ->get();

        $systemTotals = ['cash' => 0];

        foreach ($shifts as $shift) {
            $shiftTenders = $shift->counted_cash_per_payment_method ?? $shift->expected_cash_per_payment_method ?? [];
            foreach ($shiftTenders as $method => $amount) {
                $systemTotals[$method] = ($systemTotals[$method] ?? 0) + (int) $amount;
            }
        }

        return $systemTotals;
    }

    /**
     * @return Collection<int, Shift>
     */
    public static function getShifts(string $date): Collection
    {
        return Shift::with(['till', 'user'])
            ->whereDate('opened_at', $date)
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, HeldSale>
     */
    public static function getUnresolvedHeldSales(string $date): Collection
    {
        return HeldSale::with(['user', 'shift.till'])
            ->whereDate('created_at', $date)
            ->where('status', HeldSale::STATUS_HELD)
            ->get();
    }

    /**
     * @return array{sales_total: int, refunds_total: int, voids_total: int}
     */
    public static function getTransactionMetrics(string $date): array
    {
        $salesTotal = (int) Sale::whereDate('completed_at', $date)
            ->where('status', 'completed')
            ->sum('total');

        $refundsTotal = (int) SaleReturn::whereDate('completed_at', $date)
            ->where('status', SaleReturn::STATUS_COMPLETED)
            ->sum('total_refund');

        $voidsTotal = (int) Sale::whereDate('completed_at', $date)
            ->where('status', 'voided')
            ->sum('total');

        return [
            'sales_total' => $salesTotal,
            'refunds_total' => $refundsTotal,
            'voids_total' => $voidsTotal,
        ];
    }
}
