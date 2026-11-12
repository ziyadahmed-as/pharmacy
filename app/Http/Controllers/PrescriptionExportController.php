<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Services\AuditLogger;
use App\Services\PrescriptionSearch;
use App\Services\SimplePdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV and PDF export of the prescription log (PM and Owner only).
 *
 * @uc UC-23
 *
 * @fr FR-4.12, FR-7.4
 *
 * @ac AC-26
 */
class PrescriptionExportController extends Controller
{
    private const HEADER = [
        'Prescription No',
        'Date',
        'Patient',
        'Prescriber',
        'Prescriber Reg No',
        'Status',
        'Drugs (Brand / Qty Prescribed / Qty Dispensed)',
        'Registered By',
    ];

    public function __invoke(Request $request, string $format)
    {
        $viewer = $request->user();
        abort_unless($viewer, 403);
        abort_if($viewer->isSystemAdmin(), 403);
        abort_unless($viewer->hasAnyRole([Role::PharmacyManager, Role::Owner]), 403);

        $filters = $request->only([
            'q', 'patient', 'prescriber', 'drug', 'date_from', 'date_to', 'status', 'suspect_only',
        ]);

        $query = PrescriptionSearch::build($filters)->with(['customer', 'creator', 'lines.product']);

        $rows = [];
        $query->chunk(500, function ($chunk) use (&$rows) {
            foreach ($chunk as $rx) {
                $drugs = $rx->lines->map(function ($l) {
                    return "{$l->product->brand} ({$l->dose}, Prescribed: {$l->quantity_prescribed}, Dispensed: {$l->quantity_dispensed})";
                })->join('; ');

                $rows[] = [
                    $rx->prescription_number,
                    $rx->prescription_date->format('Y-m-d'),
                    $rx->customer->name,
                    $rx->prescriber_name,
                    $rx->prescriber_registration_no,
                    ucfirst(str_replace('_', ' ', $rx->status)),
                    $drugs,
                    $rx->creator->name ?? '',
                ];
            }
        });

        AuditLogger::record('prescriptions.export', null, [], [], [
            'format' => $format,
            'rows' => count($rows),
            'filters' => array_filter($filters),
        ], $viewer);

        $name = 'prescription-log-'.now()->timezone(config('pms.timezone'))->format('Ymd-His');

        if ($format === 'pdf') {
            $pdf = SimplePdf::table('Prescription Compliance Log', array_merge([self::HEADER], $rows));

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
