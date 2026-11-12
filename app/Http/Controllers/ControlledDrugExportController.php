<?php

namespace App\Http\Controllers;

use App\Models\ControlledDrugRegister;
use App\Services\AuditLogger;
use App\Services\SimplePdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV and PDF export of the controlled drug register.
 *
 * @uc UC-22
 *
 * @fr FR-4.4, FR-4.3
 *
 * @ac AC-34
 */
class ControlledDrugExportController extends Controller
{
    private const HEADER = [
        'Time',
        'Product',
        'Batch No',
        'Entry Type',
        'Quantity',
        'Running Balance',
        'Patient',
        'Prescriber',
        'Dispensed By',
        'Second Person',
        'Receipt / Notes',
    ];

    public function __invoke(Request $request, string $format)
    {
        abort_unless((bool) setting('pharmacy.handles_controlled_drugs', true), 404);
        Gate::authorize('controlled_drugs.export');

        $viewer = $request->user();
        abort_if($viewer->isSystemAdmin(), 403);

        $query = ControlledDrugRegister::with(['product', 'batch', 'user', 'secondPerson', 'sale'])
            ->orderBy('id', 'desc');

        if ($request->filled('type')) {
            $query->where('entry_type', $request->query('type'));
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->query('product_id'));
        }

        $rows = [];
        $query->chunk(500, function ($chunk) use (&$rows) {
            foreach ($chunk as $entry) {
                $rows[] = [
                    $entry->created_at->format('Y-m-d H:i:s'),
                    $entry->product->brand,
                    $entry->batch->batch_no,
                    ucfirst($entry->entry_type),
                    (string) $entry->quantity,
                    (string) $entry->running_balance,
                    $entry->patient_name ?? '',
                    $entry->prescriber_name ?? '',
                    $entry->user->name,
                    $entry->secondPerson?->name ?? '',
                    $entry->sale?->receipt_no ?? ($entry->notes ?? ''),
                ];
            }
        });

        AuditLogger::record('controlled_drugs.export', null, [], [], [
            'format' => $format,
            'rows' => count($rows),
        ], $viewer);

        $name = 'controlled-drug-register-'.now()->timezone(config('pms.timezone'))->format('Ymd-His');

        if ($format === 'pdf') {
            $pdf = SimplePdf::table('Controlled Drug Register', array_merge([self::HEADER], $rows));

            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "attachment; filename=\"{$name}.pdf\"",
            ]);
        }

        return new StreamedResponse(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM
            fputcsv($out, self::HEADER);
            foreach ($rows as $row) {
                // Neutralise spreadsheet formulas in exported text
                fputcsv($out, array_map(fn ($c) => preg_match('/^[=+\-@]/', (string) $c) ? "'".$c : $c, $row));
            }
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$name}.csv\"",
        ]);
    }
}
