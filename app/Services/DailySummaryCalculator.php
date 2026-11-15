<?php

namespace App\Services;

use App\Models\HeldSale;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Shift;
use Carbon\Carbon;
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
        $tz = config('pms.timezone', 'Africa/Addis_Ababa');
        $startUtc = Carbon::createFromFormat('Y-m-d H:i:s', $date.' 00:00:00', $tz)->utc();
        $endUtc = Carbon::createFromFormat('Y-m-d H:i:s', $date.' 23:59:59', $tz)->utc();

        $shifts = Shift::where(function ($q) use ($startUtc, $endUtc, $date) {
            $q->whereBetween('opened_at', [$startUtc, $endUtc])
                ->orWhereDate('opened_at', $date);
        })
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
        $tz = config('pms.timezone', 'Africa/Addis_Ababa');
        $startUtc = Carbon::createFromFormat('Y-m-d H:i:s', $date.' 00:00:00', $tz)->utc();
        $endUtc = Carbon::createFromFormat('Y-m-d H:i:s', $date.' 23:59:59', $tz)->utc();

        return Shift::with(['till', 'user'])
            ->where(function ($q) use ($startUtc, $endUtc, $date) {
                $q->whereBetween('opened_at', [$startUtc, $endUtc])
                    ->orWhereDate('opened_at', $date);
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, HeldSale>
     */
    public static function getUnresolvedHeldSales(string $date): Collection
    {
        $tz = config('pms.timezone', 'Africa/Addis_Ababa');
        $startUtc = Carbon::createFromFormat('Y-m-d H:i:s', $date.' 00:00:00', $tz)->utc();
        $endUtc = Carbon::createFromFormat('Y-m-d H:i:s', $date.' 23:59:59', $tz)->utc();

        return HeldSale::with(['user', 'shift.till'])
            ->where(function ($q) use ($startUtc, $endUtc, $date) {
                $q->whereBetween('created_at', [$startUtc, $endUtc])
                    ->orWhereDate('created_at', $date);
            })
            ->where('status', HeldSale::STATUS_HELD)
            ->get();
    }

    /**
     * @return array{sales_total: int, refunds_total: int, voids_total: int}
     */
    public static function getTransactionMetrics(string $date): array
    {
        $tz = config('pms.timezone', 'Africa/Addis_Ababa');
        $startUtc = Carbon::createFromFormat('Y-m-d H:i:s', $date.' 00:00:00', $tz)->utc();
        $endUtc = Carbon::createFromFormat('Y-m-d H:i:s', $date.' 23:59:59', $tz)->utc();

        $salesTotal = (int) Sale::where(function ($q) use ($startUtc, $endUtc, $date) {
            $q->whereBetween('completed_at', [$startUtc, $endUtc])
                ->orWhereDate('completed_at', $date);
        })
            ->where('status', 'completed')
            ->sum('total');

        $refundsTotal = (int) SaleReturn::where(function ($q) use ($startUtc, $endUtc, $date) {
            $q->whereBetween('completed_at', [$startUtc, $endUtc])
                ->orWhereDate('completed_at', $date);
        })
            ->where('status', SaleReturn::STATUS_COMPLETED)
            ->sum('total_refund');

        $voidsTotal = (int) Sale::where(function ($q) use ($startUtc, $endUtc, $date) {
            $q->whereBetween('completed_at', [$startUtc, $endUtc])
                ->orWhereDate('completed_at', $date);
        })
            ->where('status', 'voided')
            ->sum('total');

        return [
            'sales_total' => $salesTotal,
            'refunds_total' => $refundsTotal,
            'voids_total' => $voidsTotal,
        ];
    }
}
