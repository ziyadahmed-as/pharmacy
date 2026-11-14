<?php

namespace App\Reports\Definitions\Compliance;

use App\Enums\Role;
use App\Models\ControlledDrugRegister;
use App\Models\User;
use App\Reports\Definitions\BaseReportDefinition;

/**
 * Controlled drug register compliance report (UC-30).
 *
 * @uc UC-30 @fr FR-7.4, FR-4.4 @ac AC-29
 */
class ComplianceControlledDrugsReport extends BaseReportDefinition
{
    public function key(): string
    {
        return 'compliance_controlled_drugs';
    }

    public function name(): string
    {
        return __('reports.compliance_controlled_drugs_name');
    }

    public function category(): string
    {
        return 'compliance';
    }

    public function description(): string
    {
        return __('reports.compliance_controlled_drugs_desc');
    }

    public function canView(User $user): bool
    {
        if ($user->isSystemAdmin() || ! $user->canAccessBusinessData()) {
            return false;
        }

        // Only Owner and PM (permission controlled_drugs.view)
        return $user->hasRole(Role::Owner) || $user->hasRole(Role::PharmacyManager);
    }

    public function availableFilters(): array
    {
        return [
            'date_range' => true,
            'search' => true,
        ];
    }

    public function headers(User $user): array
    {
        return [
            __('reports.col_date'),
            __('reports.col_product'),
            __('reports.col_batch_no'),
            __('reports.col_entry_type'),
            __('reports.col_quantity'),
            __('reports.col_running_balance'),
            __('reports.col_patient_name'),
            __('reports.col_prescriber_name'),
            __('reports.col_dispensed_by'),
            __('reports.col_second_person'),
        ];
    }

    public function query(array $filters, User $user): array
    {
        [$start, $end] = $this->parseDateRange($filters);

        $query = ControlledDrugRegister::query()
            ->with(['product', 'batch', 'user', 'secondPerson'])
            ->whereDate('created_at', '>=', $start)
            ->whereDate('created_at', '<=', $end)
            ->orderBy('id');

        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($term) {
                $q->whereHas('product', fn ($p) => $p->where('brand', 'like', $term)->orWhere('generic_name', 'like', $term))
                    ->orWhereHas('batch', fn ($b) => $b->where('batch_no', 'like', $term))
                    ->orWhere('patient_name', 'like', $term)
                    ->orWhere('prescriber_name', 'like', $term);
            });
        }

        $entries = $query->get();

        $rows = [];
        foreach ($entries as $e) {
            $rows[] = [
                $this->formatDate($e->created_at),
                $e->product?->brand ?? 'Unknown',
                $e->batch?->batch_no ?? '-',
                ucfirst($e->entry_type),
                (string) $e->quantity,
                (string) $e->running_balance,
                $e->patient_name ?? '-',
                $e->prescriber_name ?? '-',
                $e->user?->name ?? 'User #'.$e->user_id,
                $e->secondPerson?->name ?? '-',
            ];
        }

        $summary = [
            'total_register_entries' => count($rows),
        ];

        return [
            'headers' => $this->headers($user),
            'rows' => $rows,
            'summary' => $summary,
            'row_count' => count($rows),
        ];
    }
}
