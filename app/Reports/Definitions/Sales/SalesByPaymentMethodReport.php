<?php

namespace App\Reports\Definitions\Sales;

use App\Enums\Role;
use App\Models\Payment;
use App\Models\SaleReturn;
use App\Models\User;
use App\Reports\Definitions\BaseReportDefinition;

/**
 * Sales report by payment method (UC-30).
 *
 * @uc UC-30 @fr FR-7.1 @ac AC-29
 */
class SalesByPaymentMethodReport extends BaseReportDefinition
{
    public function key(): string
    {
        return 'sales_by_payment_method';
    }

    public function name(): string
    {
        return __('reports.sales_by_payment_method_name');
    }

    public function category(): string
    {
        return 'sales';
    }

    public function description(): string
    {
        return __('reports.sales_by_payment_method_desc');
    }

    public function canView(User $user): bool
    {
        if ($user->isSystemAdmin() || ! $user->canAccessBusinessData()) {
            return false;
        }

        return $user->hasAnyRole([Role::Owner, Role::PharmacyManager, Role::PharmacyStaff]);
    }

    public function availableFilters(): array
    {
        return [
            'date_range' => true,
        ];
    }

    public function headers(User $user): array
    {
        return [
            __('reports.col_payment_method'),
            __('reports.col_transactions'),
            __('reports.col_received'),
            __('reports.col_refunded'),
            __('reports.col_net_received'),
        ];
    }

    public function query(array $filters, User $user): array
    {
        [$start, $end] = $this->parseDateRange($filters);
        $isStaff = $this->isStaffOnly($user);

        // Payments from completed sales
        $paymentsQuery = Payment::query()
            ->whereHas('sale', function ($q) use ($start, $end, $isStaff, $user) {
                $q->where('status', 'completed')
                    ->whereDate('completed_at', '>=', $start)
                    ->whereDate('completed_at', '<=', $end);

                if ($isStaff) {
                    $q->where('user_id', $user->id);
                }
            });

        $refundsQuery = SaleReturn::query()
            ->where('status', SaleReturn::STATUS_COMPLETED)
            ->whereDate('completed_at', '>=', $start)
            ->whereDate('completed_at', '<=', $end);

        if ($isStaff) {
            $refundsQuery->where('user_id', $user->id);
        }

        $payments = $paymentsQuery->get();
        $refunds = $refundsQuery->get();

        $methods = [];

        foreach ($payments as $p) {
            $m = $p->method;
            if (! isset($methods[$m])) {
                $methods[$m] = [
                    'label' => $p->methodLabel(),
                    'count' => 0,
                    'received' => 0,
                    'refunded' => 0,
                ];
            }

            $methods[$m]['count']++;
            $methods[$m]['received'] += $p->amount;
        }

        foreach ($refunds as $r) {
            $m = $r->payment_method;
            if (! isset($methods[$m])) {
                $methods[$m] = [
                    'label' => ucfirst(str_replace('_', ' ', $m)),
                    'count' => 0,
                    'received' => 0,
                    'refunded' => 0,
                ];
            }

            $methods[$m]['refunded'] += $r->total_refund;
        }

        ksort($methods);

        $rows = [];
        $totCount = 0;
        $totReceived = 0;
        $totRefunded = 0;
        $totNet = 0;

        foreach ($methods as $item) {
            $net = $item['received'] - $item['refunded'];
            $totCount += $item['count'];
            $totReceived += $item['received'];
            $totRefunded += $item['refunded'];
            $totNet += $net;

            $rows[] = [
                $item['label'],
                (string) $item['count'],
                $this->formatMoney($item['received']),
                $this->formatMoney($item['refunded']),
                $this->formatMoney($net),
            ];
        }

        $summary = [
            'total_transactions' => $totCount,
            'total_received' => $this->formatMoney($totReceived),
            'total_refunded' => $this->formatMoney($totRefunded),
            'total_net' => $this->formatMoney($totNet),
        ];

        return [
            'headers' => $this->headers($user),
            'rows' => $rows,
            'summary' => $summary,
            'row_count' => count($rows),
        ];
    }
}
