<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\User;
use App\Queries\CashSummaryQuery;
use App\Queries\RevenueSummaryQuery;
use App\Services\AuditLogger;
use App\Services\SimplePdf;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controller to export (CSV, Excel, PDF) and print Cash & Revenue Summaries (UC-29).
 *
 * @uc UC-29
 *
 * @fr FR-6.1, FR-6.6, FR-7.1
 *
 * @ac AC-24
 */
class CashRevenueExportController extends Controller
{
    private function authorizeViewer(?User $user): void
    {
        abort_unless($user, 403);
        abort_if($user->hasRole(Role::SystemAdmin), 403);
        abort_unless($user->hasRole(Role::Owner) || $user->hasRole(Role::PharmacyManager), 403);
    }

    public function export(Request $request, string $format): Response
    {
        $viewer = $request->user();
        $this->authorizeViewer($viewer);

        $startDate = $request->query('start_date', now()->toDateString());
        $endDate = $request->query('end_date', $startDate);

        $revenue = (new RevenueSummaryQuery($startDate, $endDate))->get();
        $cash = (new CashSummaryQuery($startDate, $endDate))->get();

        AuditLogger::record('finance.cash_revenue.export', null, [], [], [
            'format' => $format,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        $currency = setting('profile.currency', 'ETB');
        $filename = "cash-revenue-summary-{$startDate}-to-{$endDate}";

        if ($format === 'pdf') {
            $header = ['Metric / Method', 'Count', "Amount ({$currency})", 'Notes'];
            $rows = [
                ['Gross Sales', (string) $revenue['gross_sales_count'], number_format($revenue['gross_sales'] / 100, 2), 'Completed sales'],
                ['Customer Refunds', (string) $revenue['refunds_count'], '-'.number_format($revenue['refunds'] / 100, 2), 'Reduces revenue'],
                ['Net Sales', '-', number_format($revenue['net_sales'] / 100, 2), 'Gross sales minus refunds'],
                ['Voided Sales', (string) $revenue['voids_count'], number_format($revenue['voids'] / 100, 2), 'Reversed transactions'],
                ['Operating Expenses', (string) $revenue['expenses_count'], '-'.number_format($revenue['expenses'] / 100, 2), 'Recorded store expenses'],
                ['Net Cash Flow / Surplus', '-', number_format($revenue['operating_surplus'] / 100, 2), 'Net sales minus expenses'],
                ['--- TENDERS ---', '', '', ''],
            ];

            foreach ($cash['methods'] as $m) {
                $rows[] = [
                    $m['label'],
                    '-',
                    number_format($m['net'] / 100, 2),
                    'Received: '.number_format($m['received'] / 100, 2).' | Refunded: '.number_format($m['refunded'] / 100, 2),
                ];
            }

            $rows[] = ['--- LOCKED DAYS ---', '', '', ''];
            foreach ($cash['locked_days'] as $ld) {
                $rows[] = [
                    $ld['date'],
                    'Day Lock',
                    number_format($ld['system_total'] / 100, 2),
                    'Counted: '.number_format($ld['counted_total'] / 100, 2).' | Var: '.number_format($ld['total_variance'] / 100, 2).' ('.$ld['closer'].')',
                ];
            }

            $pdf = SimplePdf::table("Cash & Revenue Summary ({$startDate} to {$endDate})", array_merge([$header], $rows));

            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "attachment; filename=\"{$filename}.pdf\"",
            ]);
        }

        // CSV / Excel export
        return new StreamedResponse(function () use ($revenue, $cash, $startDate, $endDate, $currency) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel

            fputcsv($out, ['PHARMACY MANAGEMENT SYSTEM - CASH & REVENUE SUMMARY']);
            fputcsv($out, ['Period:', "{$startDate} to {$endDate}"]);
            fputcsv($out, ['Notice:', 'This is not a profit and loss statement. Cost of goods sold is not part of this view.']);
            fputcsv($out, []);

            // 1. Revenue Summary
            fputcsv($out, ['REVENUE SUMMARY']);
            fputcsv($out, ['Metric', 'Count', "Amount ({$currency})", 'Notes']);
            fputcsv($out, ['Gross Sales', $revenue['gross_sales_count'], number_format($revenue['gross_sales'] / 100, 2), 'Completed sales']);
            fputcsv($out, ['Customer Refunds', $revenue['refunds_count'], '-'.number_format($revenue['refunds'] / 100, 2), 'Refunds reduce revenue']);
            fputcsv($out, ['Net Sales', '', number_format($revenue['net_sales'] / 100, 2), 'Gross sales minus refunds']);
            fputcsv($out, ['Voided Transactions', $revenue['voids_count'], number_format($revenue['voids'] / 100, 2), 'Voided sales']);
            fputcsv($out, ['Operating Expenses', $revenue['expenses_count'], '-'.number_format($revenue['expenses'] / 100, 2), 'Recorded operating expenses']);
            fputcsv($out, ['Operating Surplus / Net Cash Flow', '', number_format($revenue['operating_surplus'] / 100, 2), 'Net sales minus operating expenses']);
            fputcsv($out, []);

            // 2. Expenses by Category
            fputcsv($out, ['OPERATING EXPENSES BREAKDOWN']);
            fputcsv($out, ['Category', "Amount ({$currency})"]);
            foreach ($revenue['expenses_by_category'] as $cat => $amount) {
                fputcsv($out, [ucfirst($cat), number_format($amount / 100, 2)]);
            }
            fputcsv($out, ['Total Operating Expenses', number_format($revenue['expenses'] / 100, 2)]);
            fputcsv($out, []);

            // 3. Cash Tender Summary
            fputcsv($out, ['CASH & TENDER SUMMARY']);
            fputcsv($out, ['Payment Method', "Gross Received ({$currency})", "Refunds Disbursed ({$currency})", "Net Cash ({$currency})"]);
            foreach ($cash['methods'] as $m) {
                fputcsv($out, [
                    $m['label'],
                    number_format($m['received'] / 100, 2),
                    number_format($m['refunded'] / 100, 2),
                    number_format($m['net'] / 100, 2),
                ]);
            }
            fputcsv($out, [
                'Total',
                number_format($cash['total_received'] / 100, 2),
                number_format($cash['total_refunded'] / 100, 2),
                number_format($cash['total_net'] / 100, 2),
            ]);
            fputcsv($out, []);

            // 4. Daily Reconciliation from Locked Days
            fputcsv($out, ['DAILY RECONCILIATION FROM LOCKED DAYS (FR-6.6)']);
            fputcsv($out, ['Date', 'Closed By', 'Verified By', 'Locked At', "System Total ({$currency})", "Counted Total ({$currency})", "Variance ({$currency})", 'Notes & Variance Reasons']);
            foreach ($cash['locked_days'] as $ld) {
                $notes = $ld['notes'] ?? '';
                if (! empty($ld['variance_reasons'])) {
                    $reasons = [];
                    foreach ($ld['variance_reasons'] as $m => $r) {
                        $reasons[] = "{$m}: {$r}";
                    }
                    $notes = ($notes ? "{$notes} | " : '').implode(', ', $reasons);
                }

                fputcsv($out, [
                    $ld['date'],
                    $ld['closer'],
                    $ld['verifier'] ?? '-',
                    $ld['locked_at'] ?? '-',
                    number_format($ld['system_total'] / 100, 2),
                    number_format($ld['counted_total'] / 100, 2),
                    number_format($ld['total_variance'] / 100, 2),
                    $notes,
                ]);
            }

            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}.csv\"",
        ]);
    }

    public function print(Request $request): View
    {
        $viewer = $request->user();
        $this->authorizeViewer($viewer);

        $startDate = $request->query('start_date', now()->toDateString());
        $endDate = $request->query('end_date', $startDate);

        $revenue = (new RevenueSummaryQuery($startDate, $endDate))->get();
        $cash = (new CashSummaryQuery($startDate, $endDate))->get();

        return view('finance.cash-revenue-print', [
            'revenue' => $revenue,
            'cash' => $cash,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'currency' => setting('profile.currency', 'ETB'),
        ]);
    }
}
