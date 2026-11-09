<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Query object for the audit screen and exports (UC-31 step 1).
 * Filters: user, role used, action key, date range, subject type.
 *
 * @uc UC-31 @fr FR-7.5,FR-8.3 @br BR-15 @ac AC-19,AC-35
 */
class AuditLogQuery
{
    /** Action prefixes the SA may see (technical entries only, E2). */
    public const TECHNICAL_PREFIXES = ['auth.', 'user.', 'approval.', 'settings.', 'audit.'];

    /** Modules whose values hold patient, sales, cost or financial data: masked for the SA. */
    public const BUSINESS_PREFIXES = ['sale.', 'stock.', 'price.', 'prescription.', 'dispense.', 'customer.', 'patient.', 'finance.', 'expense.', 'report.', 'controlled.', 'payroll.'];

    /** @param  array{user?: ?int, role?: ?string, action?: ?string, from?: ?string, to?: ?string, subject?: ?string}  $filters */
    public function __construct(private array $filters = []) {}

    public function builder(?User $viewer = null): Builder
    {
        $f = $this->filters;
        $q = AuditLog::query()->with('user')->orderByDesc('id');

        if (! empty($f['user'])) {
            $q->where('user_id', $f['user']);
        }
        if (! empty($f['role'])) {
            $q->where('role_used', $f['role']);
        }
        if (! empty($f['action'])) {
            $q->where('action', 'like', str_replace(['%', '_'], ['\%', '\_'], $f['action']).'%');
        }
        if (! empty($f['from'])) {
            $q->where('created_at', '>=', Carbon::parse($f['from'], config('pms.timezone'))->startOfDay()->utc());
        }
        if (! empty($f['to'])) {
            $q->where('created_at', '<=', Carbon::parse($f['to'], config('pms.timezone'))->endOfDay()->utc());
        }
        if (! empty($f['subject'])) {
            $q->where('auditable_type', 'like', '%'.$f['subject'].'%');
        }

        // SA: technical entries only (E2).
        if ($viewer && ! $viewer->hasPermission('audit.view') && $viewer->hasPermission('audit.view.technical')) {
            $q->where(function ($w) {
                foreach (self::TECHNICAL_PREFIXES as $p) {
                    $w->orWhere('action', 'like', $p.'%');
                }
            });
        }

        return $q;
    }

    /** Hide business values from a viewer who may not see them. @return array<string, mixed>|null */
    public static function visibleValues(?array $values, string $action, User $viewer): ?array
    {
        if ($values === null) {
            return null;
        }
        if ($viewer->hasPermission('audit.view')) {
            return $values;
        }
        foreach (self::BUSINESS_PREFIXES as $p) {
            if (str_starts_with($action, $p)) {
                return array_map(fn () => AuditLogger::MASK, $values);
            }
        }

        return $values;
    }
}
