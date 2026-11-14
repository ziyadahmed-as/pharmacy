<?php

namespace App\Reports\Definitions\Compliance;

use App\Enums\Role;
use App\Models\Prescription;
use App\Models\User;
use App\Reports\Definitions\BaseReportDefinition;

/**
 * Prescription log and dispensing compliance report (UC-30).
 *
 * @uc UC-30 @fr FR-7.4, FR-4.12 @ac AC-29
 */
class CompliancePrescriptionsReport extends BaseReportDefinition
{
    public function key(): string
    {
        return 'compliance_prescriptions';
    }

    public function name(): string
    {
        return __('reports.compliance_prescriptions_name');
    }

    public function category(): string
    {
        return 'compliance';
    }

    public function description(): string
    {
        return __('reports.compliance_prescriptions_desc');
    }

    public function canView(User $user): bool
    {
        if ($user->isSystemAdmin() || ! $user->canAccessBusinessData()) {
            return false;
        }

        return $user->hasAnyRole([Role::Owner, Role::PharmacyManager, Role::PharmacyStaff]);
    }

    public function availableFilters(): array
    {
        return [
            'date_range' => true,
            'status' => [
                Prescription::STATUS_REGISTERED,
                Prescription::STATUS_PENDING_VERIFICATION,
                Prescription::STATUS_VERIFIED,
                Prescription::STATUS_PARTIALLY_DISPENSED,
                Prescription::STATUS_FULLY_DISPENSED,
                Prescription::STATUS_REJECTED,
                Prescription::STATUS_CANCELLED,
            ],
            'search' => true,
        ];
    }

    public function headers(User $user): array
    {
        return [
            __('reports.col_prescription_no'),
            __('reports.col_prescription_date'),
            __('reports.col_patient_name'),
            __('reports.col_prescriber_name'),
            __('reports.col_status'),
            __('reports.col_items_count'),
            __('reports.col_created_by'),
            __('reports.col_registered_at'),
        ];
    }

    public function query(array $filters, User $user): array
    {
        [$start, $end] = $this->parseDateRange($filters);

        $query = Prescription::query()
            ->with(['customer', 'creator', 'lines'])
            ->whereDate('prescription_date', '>=', $start)
            ->whereDate('prescription_date', '<=', $end)
            ->orderByDesc('id');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('prescription_number', 'like', $term)
                    ->orWhere('prescriber_name', 'like', $term)
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $term)->orWhere('phone', 'like', $term));
            });
        }

        $prescriptions = $query->get();

        $rows = [];
        $statusCounts = [];

        foreach ($prescriptions as $p) {
            $statusCounts[$p->status] = ($statusCounts[$p->status] ?? 0) + 1;

            $rows[] = [
                $p->prescription_number,
                $this->formatDate($p->prescription_date, 'Y-m-d'),
                $p->customer?->name ?? 'Guest',
                $p->prescriber_name,
                ucfirst(str_replace('_', ' ', $p->status)),
                (string) $p->lines->count(),
                $p->creator?->name ?? 'User #'.$p->created_by,
                $this->formatDate($p->created_at),
            ];
        }

        $summary = [
            'total_prescriptions' => count($rows),
            'fully_dispensed' => $statusCounts[Prescription::STATUS_FULLY_DISPENSED] ?? 0,
            'verified_pending' => $statusCounts[Prescription::STATUS_VERIFIED] ?? 0,
        ];

        return [
            'headers' => $this->headers($user),
            'rows' => $rows,
            'summary' => $summary,
            'row_count' => count($rows),
        ];
    }
}
