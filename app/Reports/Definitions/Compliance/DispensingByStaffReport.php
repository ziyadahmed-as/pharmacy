<?php

namespace App\Reports\Definitions\Compliance;

use App\Enums\Role;
use App\Models\Prescription;
use App\Models\User;
use App\Reports\Definitions\BaseReportDefinition;
use Carbon\Carbon;

/**
 * Dispensing by staff report (UC-30/UC-20).
 */
class DispensingByStaffReport extends BaseReportDefinition
{
    public function key(): string
    {
        return 'dispensing_by_staff';
    }

    public function name(): string
    {
        return __('reports.dispensing_by_staff_name', [], 'en') ?: 'Dispensing by Staff';
    }

    public function category(): string
    {
        return 'compliance';
    }

    public function description(): string
    {
        return __('reports.dispensing_by_staff_desc', [], 'en') ?: 'Audit of prescriptions verified, dispensed, or cancelled by dispenser staff.';
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
            'user_id' => true,
            'status' => true,
        ];
    }

    public function headers(User $user): array
    {
        return [
            __('reports.col_dispenser', [], 'en') ?: 'Dispenser',
            __('reports.col_total_dispensed', [], 'en') ?: 'Prescriptions Dispensed',
            __('reports.col_controlled_count', [], 'en') ?: 'Controlled Drugs Dispensed',
            __('reports.col_last_dispensed', [], 'en') ?: 'Last Dispensed Date',
        ];
    }

    public function query(array $filters, User $user): array
    {
        [$start, $end] = $this->parseDateRange($filters);
        $isStaff = $this->isStaffOnly($user);

        $query = Prescription::with('dispenser')
            ->whereNotNull('dispensed_by')
            ->whereDate('dispensed_at', '>=', $start)
            ->whereDate('dispensed_at', '<=', $end)
            ->when($isStaff, fn ($q) => $q->where('dispensed_by', $user->id))
            ->when(! $isStaff && ! empty($filters['user_id']), fn ($q) => $q->where('dispensed_by', $filters['user_id']))
            ->get();

        $byUser = $query->groupBy('dispensed_by');
        $rows = [];
        $totalDispensed = 0;

        foreach ($byUser as $uId => $items) {
            $dispenser = $items->first()->dispenser;
            $name = $dispenser?->name ?? $dispenser?->username ?? ('User #'.$uId);
            $count = $items->count();
            $controlledCount = $items->filter(fn ($p) => ($p->is_controlled ?? false) || ($p->items && $p->items->contains(fn ($it) => $it->product?->isControlled())))->count();
            $lastDate = $items->max('dispensed_at');

            $totalDispensed += $count;

            $rows[] = [
                $name,
                $count,
                $controlledCount,
                $lastDate ? Carbon::parse($lastDate)->format('Y-m-d H:i') : '—',
            ];
        }

        return [
            'headers' => $this->headers($user),
            'rows' => $rows,
            'summary' => [
                'dispensers_count' => count($rows),
                'total_dispensed' => $totalDispensed,
            ],
            'row_count' => count($rows),
        ];
    }
}
