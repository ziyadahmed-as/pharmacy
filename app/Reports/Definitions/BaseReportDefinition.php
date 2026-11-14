<?php

namespace App\Reports\Definitions;

use App\Enums\Role;
use App\Models\User;
use App\Reports\Contracts\ReportDefinition;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Base report definition providing common helper methods for authorization,
 * date handling, cost visibility, and formatting.
 *
 * @uc UC-30
 *
 * @fr FR-7.1 to 7.6, 2.19, 4.12
 *
 * @ac AC-29
 */
abstract class BaseReportDefinition implements ReportDefinition
{
    public function canView(User $user): bool
    {
        // SA never sees business reports (BR-06, AGENTS.md section 5)
        if ($user->isSystemAdmin() || ! $user->canAccessBusinessData()) {
            return false;
        }

        // Default: Owner and Pharmacy Manager
        return $user->hasRole(Role::Owner) || $user->hasRole(Role::PharmacyManager);
    }

    public function canViewCost(User $user): bool
    {
        if ($user->isSystemAdmin() || ! $user->canAccessBusinessData()) {
            return false;
        }

        return $user->hasRole(Role::Owner)
            || $user->hasRole(Role::PharmacyManager)
            || $user->hasPermission('cost.view');
    }

    public function isStaffOnly(User $user): bool
    {
        return $user->hasRole(Role::PharmacyStaff)
            && ! $user->hasRole(Role::Owner)
            && ! $user->hasRole(Role::PharmacyManager);
    }

    public function isSensitive(): bool
    {
        return true;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: string, 1: string}
     */
    protected function parseDateRange(array $filters): array
    {
        $start = ! empty($filters['start_date'])
            ? Carbon::parse($filters['start_date'])->toDateString()
            : now()->startOfMonth()->toDateString();

        $end = ! empty($filters['end_date'])
            ? Carbon::parse($filters['end_date'])->toDateString()
            : now()->toDateString();

        if (Carbon::parse($start)->isAfter(Carbon::parse($end))) {
            [$start, $end] = [$end, $start];
        }

        return [$start, $end];
    }

    protected function formatMoney(?int $santim): string
    {
        return number_format(($santim ?? 0) / 100, 2);
    }

    protected function formatDate(?CarbonInterface $date, string $format = 'Y-m-d H:i'): string
    {
        return $date ? $date->format($format) : '-';
    }
}
