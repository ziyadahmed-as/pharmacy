<?php

namespace App\Actions\Owner;

use App\Enums\Role;
use App\Models\Batch;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\StockAlert;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Messaging\MessageGateway;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Dispatches an executive daily summary to the business Owner (FR-9.2).
 *
 * @uc UC-32
 *
 * @fr FR-7.7, FR-9.2
 */
class SendDailySummary
{
    public function __construct(protected MessageGateway $gateway) {}

    /**
     * @return array{success: bool, message: string, recipient: string, error?: string|null}
     */
    public function execute(User $actor, ?string $channel = 'sms'): array
    {
        // Owner only
        if (! $actor->hasRole(Role::Owner)) {
            throw new AuthorizationException(__('dashboard.owner.unauthorized'));
        }

        $today = now()->toDateString();
        $pharmacyName = (string) setting('profile.name', config('app.name', 'Pharmacy'));

        // 1. Sales & Transactions
        $grossSales = (int) Sale::where('status', 'completed')->whereDate('completed_at', $today)->sum('total');
        $salesCount = Sale::where('status', 'completed')->whereDate('completed_at', $today)->count();
        $refunds = (int) SaleReturn::where('status', 'completed')->whereDate('completed_at', $today)->sum('total_refund');
        $netSales = max(0, $grossSales - $refunds);

        // 2. Cash vs Digital
        $cashTender = (int) Payment::query()
            ->join('sales', 'payments.sale_id', '=', 'sales.id')
            ->where('sales.status', 'completed')
            ->whereDate('sales.completed_at', $today)
            ->where('payments.method', 'cash')
            ->sum('payments.amount');

        $digitalTender = (int) Payment::query()
            ->join('sales', 'payments.sale_id', '=', 'sales.id')
            ->where('sales.status', 'completed')
            ->whereDate('sales.completed_at', $today)
            ->whereIn('payments.method', ['mobile_money', 'telebirr', 'cbe_birr', 'card', 'bank_transfer'])
            ->sum('payments.amount');

        // 3. Alerts
        $lowStockCount = StockAlert::where('status', 'active')->whereIn('type', ['low_stock', 'critical_stock'])->count();
        $expiringCount = Batch::whereBetween('expiry_date', [$today, now()->addDays(90)->toDateString()])
            ->whereHas('batchStocks', fn ($q) => $q->where('quantity', '>', 0))
            ->count();

        // Recipient phone: staff profile -> pharmacy profile phone -> fallback
        $recipientPhone = $actor->staff?->phone ?: (string) setting('profile.phone');
        if (empty($recipientPhone)) {
            $recipientPhone = '+251911000000';
        }

        $summaryText = sprintf(
            "[%s] Daily Summary (%s):\nNet Sales: %s ETB (%d sales)\nCash: %s ETB | Digital: %s ETB\nLow Stock: %d items | Expiring: %d batches",
            $pharmacyName,
            now()->format('d M Y'),
            number_format($netSales / 100, 2),
            $salesCount,
            number_format($cashTender / 100, 2),
            number_format($digitalTender / 100, 2),
            $lowStockCount,
            $expiringCount
        );

        $result = $this->gateway->send($recipientPhone, $summaryText);

        AuditLogger::record(
            action: 'owner.daily_summary.sent',
            subject: null,
            old: [],
            new: [
                'recipient' => $recipientPhone,
                'channel' => $channel,
                'success' => $result['success'],
                'net_sales' => $netSales,
                'sales_count' => $salesCount,
            ],
            metadata: [
                'recipient' => $recipientPhone,
                'channel' => $channel,
            ],
            user: $actor,
            description: "Owner daily summary dispatched via {$channel} to {$recipientPhone}"
        );

        return [
            'success' => $result['success'],
            'message' => $summaryText,
            'recipient' => $recipientPhone,
            'error' => $result['error'] ?? null,
        ];
    }
}
