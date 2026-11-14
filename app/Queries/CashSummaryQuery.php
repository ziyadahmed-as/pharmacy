<?php

namespace App\Queries;

use App\Models\DailyClosing;
use App\Models\Payment;
use App\Models\SaleReturn;
use Illuminate\Support\Carbon;

/**
 * Query object for cash summary by payment method and daily reconciliation from locked days (UC-29).
 *
 * @uc UC-29
 *
 * @fr FR-6.1, FR-6.6, FR-7.1
 *
 * @ac AC-24
 */
class CashSummaryQuery
{
    private string $startDate;

    private string $endDate;

    public function __construct(string $startDate, ?string $endDate = null)
    {
        $this->startDate = Carbon::parse($startDate)->toDateString();
        $this->endDate = Carbon::parse($endDate ?? $startDate)->toDateString();
    }

    /**
     * @return array{
     *     start_date: string,
     *     end_date: string,
     *     methods: array<string, array{
     *         method: string,
     *         label: string,
     *         received: int,
     *         refunded: int,
     *         net: int
     *     }>,
     *     total_received: int,
     *     total_refunded: int,
     *     total_net: int,
     *     locked_days: array<int, array{
     *         id: int,
     *         date: string,
     *         closer: string,
     *         verifier: ?string,
     *         locked_at: ?string,
     *         system_totals: array<string, int>,
     *         counted_totals: array<string, int>,
     *         variances: array<string, int>,
     *         variance_reasons: array<string, string>,
     *         system_total: int,
     *         counted_total: int,
     *         total_variance: int,
     *         notes: ?string
     *     }>,
     *     locked_days_summary: array{
     *         days_count: int,
     *         system_total: int,
     *         counted_total: int,
     *         variance: int
     *     }
     * }
     */
    public function get(): array
    {
        // 1. Completed sales payments grouped by method
        /** @var array<string, int> $payments */
        $payments = Payment::query()
            ->whereHas('sale', function ($q) {
                $q->where('status', 'completed')
                    ->whereDate('completed_at', '>=', $this->startDate)
                    ->whereDate('completed_at', '<=', $this->endDate);
            })
            ->selectRaw('method, sum(amount) as total_amount')
            ->groupBy('method')
            ->pluck('total_amount', 'method')
            ->map(fn ($val) => (int) $val)
            ->toArray();

        // 2. Completed refunds grouped by payment method
        /** @var array<string, int> $refunds */
        $refunds = SaleReturn::query()
            ->where('status', SaleReturn::STATUS_COMPLETED)
            ->whereDate('completed_at', '>=', $this->startDate)
            ->whereDate('completed_at', '<=', $this->endDate)
            ->selectRaw('payment_method, sum(total_refund) as total_refund')
            ->groupBy('payment_method')
            ->pluck('total_refund', 'payment_method')
            ->map(fn ($val) => (int) $val)
            ->toArray();

        // Standard known payment methods
        $defaultMethods = ['cash', 'mobile_money', 'telebirr', 'cbe_birr', 'card', 'bank_transfer'];
        $allMethodKeys = array_values(array_unique(array_merge(
            $defaultMethods,
            array_keys($payments),
            array_keys($refunds)
        )));

        $methodSummaries = [];
        $totalReceived = 0;
        $totalRefunded = 0;

        foreach ($allMethodKeys as $methodKey) {
            $rec = (int) ($payments[$methodKey] ?? 0);
            $ref = (int) ($refunds[$methodKey] ?? 0);
            $net = $rec - $ref;

            // Only include in summary if non-zero or standard method
            if ($rec !== 0 || $ref !== 0 || in_array($methodKey, ['cash', 'mobile_money', 'telebirr', 'card', 'bank_transfer'], true)) {
                $methodSummaries[$methodKey] = [
                    'method' => $methodKey,
                    'label' => $this->methodLabel($methodKey),
                    'received' => $rec,
                    'refunded' => $ref,
                    'net' => $net,
                ];

                $totalReceived += $rec;
                $totalRefunded += $ref;
            }
        }

        // 3. Daily reconciliation figures from locked days (FR-6.6, AC-24)
        $lockedClosings = DailyClosing::with(['closer', 'verifier'])
            ->where('status', DailyClosing::STATUS_LOCKED)
            ->whereDate('date', '>=', $this->startDate)
            ->whereDate('date', '<=', $this->endDate)
            ->orderBy('date')
            ->get();

        $lockedDaysList = [];
        $lockedTotalSystem = 0;
        $lockedTotalCounted = 0;
        $lockedTotalVariance = 0;

        foreach ($lockedClosings as $closing) {
            $sys = (int) array_sum($closing->system_totals_by_method ?? []);
            $cnt = (int) array_sum($closing->counted_totals_by_method ?? []);
            $var = (int) array_sum($closing->variances_by_method ?? []);

            $lockedDaysList[] = [
                'id' => $closing->id,
                'date' => $closing->date,
                'closer' => $closing->closer?->name ?? 'System',
                'verifier' => $closing->verifier?->name,
                'locked_at' => $closing->locked_at?->format('Y-m-d H:i'),
                'system_totals' => $closing->system_totals_by_method ?? [],
                'counted_totals' => $closing->counted_totals_by_method ?? [],
                'variances' => $closing->variances_by_method ?? [],
                'variance_reasons' => $closing->variance_reasons ?? [],
                'system_total' => $sys,
                'counted_total' => $cnt,
                'total_variance' => $var,
                'notes' => $closing->notes,
            ];

            $lockedTotalSystem += $sys;
            $lockedTotalCounted += $cnt;
            $lockedTotalVariance += $var;
        }

        return [
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'methods' => $methodSummaries,
            'total_received' => $totalReceived,
            'total_refunded' => $totalRefunded,
            'total_net' => $totalReceived - $totalRefunded,
            'locked_days' => $lockedDaysList,
            'locked_days_summary' => [
                'days_count' => count($lockedDaysList),
                'system_total' => $lockedTotalSystem,
                'counted_total' => $lockedTotalCounted,
                'variance' => $lockedTotalVariance,
            ],
        ];
    }

    private function methodLabel(string $method): string
    {
        return match ($method) {
            'cash' => __('sales.payment_method_cash'),
            'mobile_money' => __('sales.payment_method_mobile_money'),
            'telebirr' => __('sales.payment_method_telebirr'),
            'cbe_birr' => __('sales.payment_method_cbe_birr'),
            'card' => __('sales.payment_method_card'),
            'bank_transfer' => __('sales.payment_method_bank_transfer'),
            default => ucfirst(str_replace('_', ' ', $method)),
        };
    }
}
