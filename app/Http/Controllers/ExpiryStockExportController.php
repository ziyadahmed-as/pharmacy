<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use App\Services\ExpiryStockWidgetQuery;
use App\Services\ProductImageService;
use App\Services\SimplePdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export the Expiry & Stock widget as CSV or PDF (UC-32, FR-7.7).
 * Cost columns are only included when the viewer has cost.view permission (BR-06).
 */
class ExpiryStockExportController extends Controller
{
    public function __invoke(Request $request, string $format): mixed
    {
        $user = $request->user();
        abort_unless($user && $user->hasPermission('stock.view'), 403);

        $canViewCost = $user->hasPermission('cost.view');

        $filters = array_filter($request->only([
            'sort_by', 'status', 'category_id', 'classification',
            'search', 'sold_from', 'sold_to',
        ]));

        $rows  = (new ExpiryStockWidgetQuery($filters))->forExport($user);
        $today = now()->toDateString();

        $header = [
            __('catalogue.product_name'),
            __('catalogue.batch_no'),
            __('catalogue.mfg_date'),
            __('catalogue.expiry_date'),
            __('catalogue.days_left'),
            __('catalogue.quantity'),
            __('catalogue.qty_sold'),
            __('catalogue.selling_price') . ' (ETB)',
            __('common.status'),
        ];

        if ($canViewCost) {
            $header[] = __('catalogue.unit_cost') . ' (ETB)';
            $header[] = __('catalogue.stock_value') . ' (ETB)';
        }

        $data = [];
        foreach ($rows as $row) {
            $expiryColour = \App\Services\NearestExpiryQuery::expiryColour(
                is_numeric($row->days_left) ? (int) $row->days_left : null
            );

            $line = [
                $row->brand . ($row->strength ? ' ' . $row->strength : '') . ($row->dosage_form ? ' ' . $row->dosage_form : ''),
                $row->batch_no,
                $row->manufacturing_date ?? '—',
                $row->expiry_date,
                is_numeric($row->days_left) ? (int) $row->days_left : '—',
                (int) ($row->qty_remaining ?? 0),
                (int) ($row->qty_sold ?? 0),
                $row->selling_price ? number_format($row->selling_price / 100, 2) : '—',
                $row->batch_status ?? '—',
            ];

            if ($canViewCost) {
                $line[] = $row->unit_cost ? number_format($row->unit_cost / 100, 2) : '—';
                $line[] = ($row->stock_value_at_cost && $row->stock_value_at_cost > 0)
                    ? number_format($row->stock_value_at_cost / 100, 2)
                    : '—';
            }

            $data[] = $line;
        }

        AuditLogger::record('stock.expiry_widget.export', null, [], [], [
            'format'  => $format,
            'rows'    => count($data),
            'filters' => $filters,
        ], $user);

        $filename = 'expiry-stock-' . now()->format('Ymd-His');

        if ($format === 'pdf') {
            $pdf = SimplePdf::table(__('catalogue.expiry_stock_widget_title'), array_merge([$header], $data));

            return response($pdf, 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => "attachment; filename=\"{$filename}.pdf\"",
            ]);
        }

        // CSV / Excel
        return new StreamedResponse(function () use ($header, $data) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
            fputcsv($out, $header);
            foreach ($data as $row) {
                fputcsv($out, array_map(
                    fn ($c) => preg_match('/^[=+\-@]/', (string) $c) ? "'" . $c : $c,
                    $row
                ));
            }
            fclose($out);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}.csv\"",
        ]);
    }
}
