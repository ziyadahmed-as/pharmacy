<?php

namespace App\Actions\Sales;

use App\Enums\Role;
use App\Models\SalesTarget;
use App\Models\Staff;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Set or update a monthly sales target for the pharmacy as a whole or an individual staff member.
 *
 * @uc UC-32
 *
 * @fr FR-7.7
 */
class SetSalesTarget
{
    /**
     * @param array{
     *     staff_id?: int|null,
     *     period: string,
     *     amount: int
     * } $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(User $user, array $data): SalesTarget
    {
        // Only PM and Owner may set sales targets (SA forbidden)
        if ($user->hasRole(Role::SystemAdmin) || ! $user->hasAnyRole([Role::PharmacyManager, Role::Owner])) {
            throw new AuthorizationException(__('sales.targets.unauthorized'));
        }

        $period = trim((string) ($data['period'] ?? ''));
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            throw ValidationException::withMessages([
                'period' => __('sales.targets.invalid_period'),
            ]);
        }

        $amount = (int) ($data['amount'] ?? 0);
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => __('sales.targets.amount_required'),
            ]);
        }

        $staffId = ! empty($data['staff_id']) ? (int) $data['staff_id'] : null;
        if ($staffId !== null) {
            $staff = Staff::find($staffId);
            if (! $staff) {
                throw ValidationException::withMessages([
                    'staff_id' => __('sales.targets.staff_not_found'),
                ]);
            }
        }

        $target = SalesTarget::updateOrCreate(
            [
                'staff_id' => $staffId,
                'period' => $period,
            ],
            [
                'amount' => $amount,
                'created_by' => $user->id,
            ]
        );

        AuditLogger::record(
            action: 'sales_target.saved',
            subject: $target,
            new: [
                'staff_id' => $staffId,
                'period' => $period,
                'amount' => $amount,
            ],
            user: $user,
            description: sprintf(
                'Set sales target for %s (%s): %s',
                $staffId ? "Staff #{$staffId}" : 'Whole Pharmacy',
                $period,
                number_format($amount / 100, 2).' ETB'
            )
        );

        return $target;
    }
}
