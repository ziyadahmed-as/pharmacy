<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use App\Services\AuditLogQuery;
use App\Services\SimplePdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** CSV and PDF export of exactly the rows the viewer may see; the export is itself audited. */
class AuditExportController extends Controller
{
    private const HEADER = ['time', 'user', 'role_used', 'action', 'subject', 'terminal', 'old_values', 'new_values'];

    public function __invoke(Request $request, string $format)
    {
        Gate::authorize('audit.export');
        $viewer = $request->user();

        $filters = array_filter($request->only(['user', 'role', 'action', 'from', 'to', 'subject']));
        $query = new AuditLogQuery($filters);

        $rows = [];
        $query->builder($viewer)->reorder('id')->chunk(500, function ($chunk) use (&$rows, $viewer) {
            foreach ($chunk as $log) {
                $old = AuditLogQuery::visibleValues($log->old_values, $log->action, $viewer);
                $new = AuditLogQuery::visibleValues($log->new_values, $log->action, $viewer);
                $rows[] = [
                    local_time($log->created_at, 'Y-m-d H:i:s'),
                    $log->user?->username ?? '',
                    $log->role_used ?? '',
                    $log->action,
                    $log->auditable_type ? class_basename($log->auditable_type).'#'.$log->auditable_id : '',
                    $log->terminal ?? '',
                    $old ? json_encode($old, JSON_UNESCAPED_UNICODE) : '',
                    $new ? json_encode($new, JSON_UNESCAPED_UNICODE) : '',
                ];
            }
        });

        AuditLogger::record('audit.export', null, [], [], ['format' => $format, 'rows' => count($rows), 'filters' => $filters]);

        $name = 'audit-'.now()->timezone(config('pms.timezone'))->format('Ymd-His');

        if ($format === 'pdf') {
            $pdf = SimplePdf::table('PMS audit log', array_merge([self::HEADER], $rows));

            return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => "attachment; filename=\"{$name}.pdf\""]);
        }

        return new StreamedResponse(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows Amharic correctly
            fputcsv($out, self::HEADER);
            foreach ($rows as $row) {
                // Neutralise spreadsheet formulas in exported text.
                fputcsv($out, array_map(fn ($c) => preg_match('/^[=+\-@]/', (string) $c) ? "'".$c : $c, $row));
            }
            fclose($out);
        }, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => "attachment; filename=\"{$name}.csv\""]);
    }
}
