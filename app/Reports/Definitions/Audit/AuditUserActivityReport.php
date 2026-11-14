<?php

namespace App\Reports\Definitions\Audit;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\User;
use App\Reports\Definitions\BaseReportDefinition;

/**
 * User activity audit report (UC-30).
 *
 * @uc UC-30 @fr FR-7.5, FR-8.3 @ac AC-29
 */
class AuditUserActivityReport extends BaseReportDefinition
{
    public function key(): string
    {
        return 'audit_user_activity';
    }

    public function name(): string
    {
        return __('reports.audit_user_activity_name');
    }

    public function category(): string
    {
        return 'audit';
    }

    public function description(): string
    {
        return __('reports.audit_user_activity_desc');
    }

    public function canView(User $user): bool
    {
        if ($user->isSystemAdmin() || ! $user->canAccessBusinessData()) {
            return false;
        }

        // Only Owner and PM (permission audit.view; staff denied)
        return $user->hasRole(Role::Owner) || $user->hasRole(Role::PharmacyManager);
    }

    public function availableFilters(): array
    {
        return [
            'date_range' => true,
            'user_id' => true,
            'search' => true,
        ];
    }

    public function headers(User $user): array
    {
        return [
            __('reports.col_timestamp'),
            __('reports.col_user'),
            __('reports.col_role_used'),
            __('reports.col_action'),
            __('reports.col_category'),
            __('reports.col_subject_type'),
            __('reports.col_subject_id'),
            __('reports.col_terminal'),
        ];
    }

    public function query(array $filters, User $user): array
    {
        [$start, $end] = $this->parseDateRange($filters);

        $query = AuditLog::query()
            ->with('user')
            ->whereDate('created_at', '>=', $start)
            ->whereDate('created_at', '<=', $end)
            ->orderByDesc('id');

        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('action', 'like', $term)
                    ->orWhere('event_category', 'like', $term)
                    ->orWhere('terminal', 'like', $term);
            });
        }

        $logs = $query->limit(500)->get();

        $rows = [];
        foreach ($logs as $log) {
            $rows[] = [
                $this->formatDate($log->created_at),
                $log->user?->name ?? 'User #'.$log->user_id,
                $log->role_used ? ucfirst(str_replace('_', ' ', $log->role_used)) : '-',
                $log->action,
                $log->event_category ?? '-',
                $log->auditable_type ? class_basename($log->auditable_type) : '-',
                $log->auditable_id ?? '-',
                $log->terminal ?? '-',
            ];
        }

        $summary = [
            'total_audit_events' => count($rows),
        ];

        return [
            'headers' => $this->headers($user),
            'rows' => $rows,
            'summary' => $summary,
            'row_count' => count($rows),
        ];
    }
}
