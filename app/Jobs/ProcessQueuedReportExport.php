<?php

namespace App\Jobs;

use App\Models\ReportQueuedExport;
use App\Reports\ReportRegistry;
use App\Services\AuditLogger;
use App\Services\Notifier;
use App\Services\SimplePdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessQueuedReportExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $exportId;

    public function __construct(int $exportId)
    {
        $this->exportId = $exportId;
    }

    public function handle(ReportRegistry $registry): void
    {
        $export = ReportQueuedExport::with('user')->find($this->exportId);
        if (! $export || $export->status !== ReportQueuedExport::STATUS_PENDING) {
            return;
        }

        $export->update(['status' => ReportQueuedExport::STATUS_PROCESSING]);

        try {
            $user = $export->user;
            $definition = $registry->get($export->report_key);

            if (! $definition || ! $definition->canView($user)) {
                $export->update([
                    'status' => ReportQueuedExport::STATUS_FAILED,
                    'error' => 'Unauthorized or missing report definition.',
                ]);

                return;
            }

            $filters = $export->filters ?? [];
            $result = $definition->query($filters, $user);
            $headers = $result['headers'];
            $rows = $result['rows'];
            $format = strtolower($export->format);

            $filename = "report-{$export->report_key}-{$export->id}-" . now()->format('Ymd_His') . '.' . ($format === 'pdf' ? 'pdf' : 'csv');
            $relativeDir = 'reports/exports/' . $user->id;
            $relativePath = $relativeDir . '/' . $filename;

            if ($format === 'pdf') {
                $pdfContent = SimplePdf::table($definition->name(), array_merge([$headers], $rows));
                Storage::disk('local')->put($relativePath, $pdfContent);
            } else {
                // CSV
                $stream = fopen('php://temp', 'r+');
                fwrite($stream, "\xEF\xBB\xBF"); // BOM for Excel
                fputcsv($stream, $headers);
                foreach ($rows as $row) {
                    fputcsv($stream, array_map(function ($val) {
                        $str = (string) $val;

                        return preg_match('/^[=+\-@\t\r]/', $str) ? "'" . $str : $str;
                    }, $row));
                }
                rewind($stream);
                Storage::disk('local')->put($relativePath, stream_get_contents($stream));
                fclose($stream);
            }

            $export->update([
                'status' => ReportQueuedExport::STATUS_COMPLETED,
                'file_path' => $relativePath,
                'filename' => $filename,
                'row_count' => count($rows),
                'completed_at' => now(),
            ]);

            // Audit
            AuditLogger::record('report.queued_export_completed', null, [], [], [
                'export_id' => $export->id,
                'report' => $export->report_key,
                'format' => $format,
                'row_count' => count($rows),
            ]);

            // Notify user
            Notifier::notify(
                $user,
                "Report export for {$definition->name()} is ready for download.",
                'report.exported',
                ['export_id' => $export->id, 'filename' => $filename]
            );
        } catch (Throwable $e) {
            $export->update([
                'status' => ReportQueuedExport::STATUS_FAILED,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
