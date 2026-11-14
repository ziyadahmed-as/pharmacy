<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Reports\ReportRegistry;
use App\Services\AuditLogger;
use App\Services\SimplePdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export and print controller for reports (UC-30).
 *
 * @uc UC-30 @fr FR-7.6 @ac AC-29
 */
class ReportExportController extends Controller
{
    public function export(Request $request, string $type, string $format, ReportRegistry $registry)
    {
        /** @var User|null $user */
        $user = $request->user();
        abort_if(! $user || $user->isSystemAdmin() || ! $user->canAccessBusinessData(), 403);

        $definition = $registry->get($type);
        abort_unless($definition && $definition->canView($user), 403);

        $filters = $request->only([
            'start_date', 'end_date', 'group_by', 'search',
            'category_id', 'user_id', 'status', 'method', 'reason', 'threshold_days',
        ]);

        $result = $definition->query($filters, $user);
        $headers = $result['headers'];
        $rows = $result['rows'];

        // Audit log export (STEP 3)
        AuditLogger::record('report.exported', null, [], [], [
            'report' => $type,
            'format' => $format,
            'row_count' => count($rows),
            'filters' => $filters,
        ]);

        $filename = "report-{$type}-".now()->format('Ymd-His');

        if ($format === 'pdf') {
            $pdfContent = SimplePdf::table(
                $definition->name(),
                array_merge([$headers], $rows)
            );

            return response($pdfContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "attachment; filename=\"{$filename}.pdf\"",
            ]);
        }

        // CSV / Excel export
        return new StreamedResponse(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
            fputcsv($out, $headers);

            foreach ($rows as $row) {
                // Neutralise CSV formula injection
                fputcsv($out, array_map(function ($val) {
                    $str = (string) $val;

                    return preg_match('/^[=+\-@\t\r]/', $str) ? "'".$str : $str;
                }, $row));
            }

            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}.csv\"",
        ]);
    }

    public function print(Request $request, string $type, ReportRegistry $registry)
    {
        /** @var User|null $user */
        $user = $request->user();
        abort_if(! $user || $user->isSystemAdmin() || ! $user->canAccessBusinessData(), 403);

        $definition = $registry->get($type);
        abort_unless($definition && $definition->canView($user), 403);

        $filters = $request->only([
            'start_date', 'end_date', 'group_by', 'search',
            'category_id', 'user_id', 'status', 'method', 'reason', 'threshold_days',
        ]);

        $result = $definition->query($filters, $user);

        return view('reports.print', [
            'definition' => $definition,
            'headers' => $result['headers'],
            'rows' => $result['rows'],
            'summary' => $result['summary'],
            'filters' => $filters,
            'pharmacyName' => setting('profile.name', config('app.name', 'Pharmacy Management System')),
            'generatedAt' => now()->format('Y-m-d H:i:s'),
            'generatedBy' => $user->name,
        ]);
    }
}
