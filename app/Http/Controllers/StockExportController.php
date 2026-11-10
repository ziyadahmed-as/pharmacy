<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\LedgerQuery;
use App\Services\ReorderListQuery;
use App\Services\SimplePdf;
use App\Services\StockOnHandQuery;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export stock on hand and stock ledger to CSV or PDF (UC-11 Step 4).
 * Restricted strictly to Owner and Pharmacy Manager (BR-06).
 *
 * @uc UC-11 @fr FR-2.10, 7.2 @br BR-06 @ac AC-16
 */
class StockExportController extends Controller
{
    private function authorizeExport(?User $user): void
    {
        abort_unless(
            $user && ($user->hasRole(Role::Owner) || $user->hasRole(Role::PharmacyManager) || $user->hasPermission('cost.view')),
            403,
            __('catalogue.unauthorized_export')
        );
    }

    public function stockOnHand(Request $request, string $format)
    {
        $viewer = $request->user();
        $this->authorizeExport($viewer);

        $filters = array_filter($request->only(['search', 'filter', 'category_id']));
        $query = new StockOnHandQuery($filters);
        $products = $query->builder($viewer)->get();

        $header = [
            'Product / Brand',
            'Generic Name',
            'Category',
            'Barcode',
            'Batch #',
            'Expiry Date',
            'Location',
            'Status',
            'Quantity',
            'Unit Cost (ETB)',
            'Valuation (ETB)',
        ];

        $rows = [];
        foreach ($products as $p) {
            if ($p->batches->isEmpty()) {
                $rows[] = [
                    $p->brand,
                    $p->generic_name,
                    $p->category?->name ?? '',
                    $p->barcode ?? '',
                    '-',
                    '-',
                    '-',
                    '-',
                    '0',
                    '0.00',
                    '0.00',
                ];

                continue;
            }

            foreach ($p->batches as $batch) {
                $unitCostEtb = $batch->unit_cost !== null ? number_format($batch->unit_cost / 100, 2) : '0.00';
                $exp = $batch->expiry_date?->format('Y-m-d') ?? '-';

                if ($batch->stocks->isEmpty()) {
                    $rows[] = [
                        $p->brand,
                        $p->generic_name,
                        $p->category?->name ?? '',
                        $p->barcode ?? '',
                        $batch->batch_no,
                        $exp,
                        '-',
                        'sellable',
                        '0',
                        $unitCostEtb,
                        '0.00',
                    ];

                    continue;
                }

                foreach ($batch->stocks as $stock) {
                    $valEtb = ($stock->status === 'sellable' && $batch->unit_cost !== null)
                        ? number_format(($stock->quantity * $batch->unit_cost) / 100, 2)
                        : '0.00';
                    $rows[] = [
                        $p->brand,
                        $p->generic_name,
                        $p->category?->name ?? '',
                        $p->barcode ?? '',
                        $batch->batch_no,
                        $exp,
                        $stock->location ?? '-',
                        $stock->status,
                        (string) $stock->quantity,
                        $unitCostEtb,
                        $valEtb,
                    ];
                }
            }
        }

        AuditLogger::record('stock.export', null, [], [], [
            'type' => 'stock_on_hand',
            'format' => $format,
            'rows' => count($rows),
            'filters' => $filters,
        ]);

        $name = 'stock-on-hand-'.now()->timezone(config('pms.timezone'))->format('Ymd-His');

        if ($format === 'pdf') {
            $pdf = SimplePdf::table('PMS Stock on Hand', array_merge([$header], $rows));

            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "attachment; filename=\"{$name}.pdf\"",
            ]);
        }

        return new StreamedResponse(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header);
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($c) => preg_match('/^[=+\-@]/', (string) $c) ? "'".$c : $c, $row));
            }
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$name}.csv\"",
        ]);
    }

    public function ledger(Request $request, string $format)
    {
        $viewer = $request->user();
        $this->authorizeExport($viewer);

        $filters = array_filter($request->only(['product_id', 'batch_id', 'type', 'from', 'to', 'search']));
        $query = new LedgerQuery($filters);

        $header = [
            'Date & Time',
            'Product',
            'Batch #',
            'Type',
            'In / Out',
            'Balance After',
            'User',
            'Source Reference',
        ];

        $rows = [];
        $query->builder($viewer)->chunk(500, function ($chunk) use (&$rows) {
            foreach ($chunk as $m) {
                $sourceInfo = LedgerQuery::sourceInfo($m);
                $rows[] = [
                    local_time($m->created_at, 'Y-m-d H:i:s'),
                    $m->batch?->product?->brand ?? '-',
                    $m->batch?->batch_no ?? '-',
                    $m->type,
                    $m->quantity > 0 ? "+{$m->quantity}" : (string) $m->quantity,
                    (string) $m->balance_after,
                    $m->user?->username ?? '-',
                    $sourceInfo['label'],
                ];
            }
        });

        AuditLogger::record('stock.export', null, [], [], [
            'type' => 'stock_ledger',
            'format' => $format,
            'rows' => count($rows),
            'filters' => $filters,
        ]);

        $name = 'stock-ledger-'.now()->timezone(config('pms.timezone'))->format('Ymd-His');

        if ($format === 'pdf') {
            $pdf = SimplePdf::table('PMS Stock Movement Ledger', array_merge([$header], $rows));

            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "attachment; filename=\"{$name}.pdf\"",
            ]);
        }

        return new StreamedResponse(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header);
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($c) => preg_match('/^[=+\-@]/', (string) $c) ? "'".$c : $c, $row));
            }
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$name}.csv\"",
        ]);
    }

    public function reorderList(Request $request, string $format)
    {
        $viewer = $request->user();
        $this->authorizeExport($viewer);

        $items = ReorderListQuery::get();

        $header = [
            'Product / Brand',
            'Generic Name',
            'Category',
            'Preferred Supplier',
            'On Hand',
            'Reorder Level',
            'Max Level',
            'Suggested Order Qty',
            'Status',
        ];

        $rows = [];
        foreach ($items as $item) {
            $rows[] = [
                $item['product']->brand,
                $item['product']->generic_name,
                $item['product']->category?->name ?? '',
                $item['preferred_supplier'],
                (string) $item['on_hand'],
                (string) $item['reorder_level'],
                $item['max_level'] !== null ? (string) $item['max_level'] : '-',
                (string) $item['suggested_qty'],
                $item['is_critical'] ? 'CRITICAL (Below Min)' : 'Low Stock',
            ];
        }

        AuditLogger::record('reorder.export', null, [], [], [
            'format' => $format,
            'rows' => count($rows),
        ]);

        $name = 'reorder-list-'.now()->timezone(config('pms.timezone'))->format('Ymd-His');

        if ($format === 'pdf') {
            $pdf = SimplePdf::table('PMS Reorder List (Orders placed outside PMS)', array_merge([$header], $rows));

            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "attachment; filename=\"{$name}.pdf\"",
            ]);
        }

        return new StreamedResponse(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header);
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($c) => preg_match('/^[=+\-@]/', (string) $c) ? "'".$c : $c, $row));
            }
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$name}.csv\"",
        ]);
    }
}
