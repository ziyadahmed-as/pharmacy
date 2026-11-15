<?php

namespace App\Reports\Definitions\Compliance;

use App\Enums\Role;
use App\Models\QuarantineRecord;
use App\Models\Recall;
use App\Models\User;
use App\Reports\Definitions\BaseReportDefinition;

/**
 * Recall traceability compliance report (UC-10, UC-30).
 *
 * @uc UC-10, UC-30 @fr FR-7.4, FR-2.11, FR-2.12 @ac AC-29
 */
class ComplianceRecallsReport extends BaseReportDefinition
{
    public function key(): string
    {
        return 'compliance_recalls';
    }

    public function name(): string
    {
        return __('reports.compliance_recalls_name');
    }

    public function category(): string
    {
        return 'compliance';
    }

    public function description(): string
    {
        return __('reports.compliance_recalls_desc');
    }

    public function canView(User $user): bool
    {
        if ($user->isSystemAdmin() || ! $user->canAccessBusinessData()) {
            return false;
        }

        return $user->hasRole(Role::Owner) || $user->hasRole(Role::PharmacyManager);
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
            __('reports.col_date'),
            __('reports.col_recall_no'),
            __('reports.col_product'),
            __('reports.col_batch_no'),
            __('reports.col_expiry_date'),
            __('reports.col_quarantined_qty'),
            __('reports.col_disposed_qty'),
            __('reports.col_remaining_quarantined'),
            __('reports.col_status'),
            __('reports.col_note'),
        ];
    }

    public function query(array $filters, User $user): array
    {
        [$start, $end] = $this->parseDateRange($filters);

        $recalls = Recall::query()
            ->with(['product', 'batches.batch.quarantineRecords.disposals', 'creator'])
            ->whereDate('recall_date', '>=', $start)
            ->whereDate('recall_date', '<=', $end)
            ->orderByDesc('id')
            ->get();

        $rows = [];
        $totRecalled = 0;
        $totDisposed = 0;
        $processedBatchIds = [];

        foreach ($recalls as $recall) {
            foreach ($recall->batches as $recallBatch) {
                $batch = $recallBatch->batch;
                $processedBatchIds[] = $recallBatch->batch_id;

                $disposedQty = 0;
                $quarantinedQty = $recallBatch->quantity_quarantined;

                if ($batch) {
                    $disposedQty = (int) $batch->quarantineRecords
                        ->flatMap->disposals
                        ->where('status', 'approved')
                        ->sum('quantity');
                }

                $remaining = max(0, $quarantinedQty - $disposedQty);

                $totRecalled += $quarantinedQty;
                $totDisposed += $disposedQty;

                $rows[] = [
                    $this->formatDate($recall->recall_date),
                    $recall->recall_no,
                    $recall->product?->brand ?? 'Unknown',
                    $batch?->batch_no ?? '-',
                    $this->formatDate($batch?->expiry_date, 'Y-m-d'),
                    (string) $quarantinedQty,
                    (string) $disposedQty,
                    (string) $remaining,
                    ucfirst($recall->status),
                    $recall->reason ?? '-',
                ];
            }
        }

        // Also include any legacy QuarantineRecord entries with reason = 'recalled'
        // that were not initiated through the Recall model
        $legacyRecords = QuarantineRecord::query()
            ->with(['batch.product', 'disposals'])
            ->where('reason', QuarantineRecord::REASON_RECALLED)
            ->whereNotIn('batch_id', $processedBatchIds)
            ->whereDate('created_at', '>=', $start)
            ->whereDate('created_at', '<=', $end)
            ->orderByDesc('id')
            ->get();

        foreach ($legacyRecords as $rec) {
            $disposedQty = (int) $rec->disposals->where('status', 'approved')->sum('quantity');
            $remaining = max(0, $rec->quantity - $disposedQty);

            $totRecalled += $rec->quantity;
            $totDisposed += $disposedQty;

            $rows[] = [
                $this->formatDate($rec->created_at),
                '-',
                $rec->batch?->product?->brand ?? 'Unknown',
                $rec->batch?->batch_no ?? '-',
                $this->formatDate($rec->batch?->expiry_date, 'Y-m-d'),
                (string) $rec->quantity,
                (string) $disposedQty,
                (string) $remaining,
                ucfirst($rec->status),
                $rec->note ?? '-',
            ];
        }

        $summary = [
            'total_recall_events' => count($rows),
            'total_recalled_units' => $totRecalled,
            'total_disposed_units' => $totDisposed,
        ];

        return [
            'headers' => $this->headers($user),
            'rows' => $rows,
            'summary' => $summary,
            'row_count' => count($rows),
        ];
    }
}
