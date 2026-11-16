<?php

namespace App\Reports\Definitions\Audit;

use App\Models\ApprovalRequest;
use App\Models\User;
use App\Reports\Definitions\BaseReportDefinition;
use Illuminate\Support\Facades\Schema;

/**
 * Approvals log report (UC-30/UC-35/UC-39).
 * Who requested, who approved, outcome, and duration.
 */
class ApprovalsLogReport extends BaseReportDefinition
{
    public function key(): string
    {
        return 'approvals_log';
    }

    public function name(): string
    {
        return __('reports.approvals_log_name', [], 'en') ?: 'Approvals Log & Turnaround';
    }

    public function category(): string
    {
        return 'audit';
    }

    public function description(): string
    {
        return __('reports.approvals_log_desc', [], 'en') ?: 'Audit log of dual-control requests: who approved what, decisions, and turnaround duration.';
    }

    public function availableFilters(): array
    {
        return [
            'date_range' => true,
            'status' => true,
        ];
    }

    public function headers(User $user): array
    {
        return [
            __('reports.col_request_id', [], 'en') ?: 'Req #',
            __('reports.col_type', [], 'en') ?: 'Type',
            __('reports.col_summary', [], 'en') ?: 'Summary',
            __('reports.col_requested_by', [], 'en') ?: 'Requested By',
            __('reports.col_approved_by', [], 'en') ?: 'Decided By',
            __('reports.col_status', [], 'en') ?: 'Status',
            __('reports.col_turnaround', [], 'en') ?: 'Turnaround',
            __('reports.col_created_at', [], 'en') ?: 'Requested At',
        ];
    }

    public function query(array $filters, User $user): array
    {
        [$start, $end] = $this->parseDateRange($filters);

        if (! Schema::hasTable('approval_requests')) {
            return [
                'headers' => $this->headers($user),
                'rows' => [],
                'summary' => [],
                'row_count' => 0,
            ];
        }

        $requests = ApprovalRequest::with(['initiator', 'approver'])
            ->whereDate('created_at', '>=', $start)
            ->whereDate('created_at', '<=', $end)
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->orderByDesc('created_at')
            ->get();

        $rows = [];

        foreach ($requests as $r) {
            $turnaround = '—';
            if ($r->decided_at && $r->created_at) {
                $hours = round($r->created_at->diffInMinutes($r->decided_at) / 60, 1);
                $turnaround = $hours.' hrs';
            }

            $rows[] = [
                '#'.$r->id,
                ucwords(str_replace('_', ' ', $r->type)),
                $r->summary ?? '—',
                $r->initiator?->name ?? '—',
                $r->approver?->name ?? 'Pending',
                ucfirst($r->status),
                $turnaround,
                $r->created_at ? $r->created_at->format('Y-m-d H:i') : '—',
            ];
        }

        return [
            'headers' => $this->headers($user),
            'rows' => $rows,
            'summary' => [
                'total_requests' => count($requests),
                'pending_count' => $requests->where('status', 'pending')->count(),
            ],
            'row_count' => count($rows),
        ];
    }
}
