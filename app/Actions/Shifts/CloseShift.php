<?php

namespace App\Actions\Shifts;

use App\Enums\Role;
use App\Models\Shift;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ShiftExpectedCalculator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Close an active shift, reconcile counted tenders against expected figures,
 * compute variance, and record closing audit.
 *
 * @uc UC-17
 *
 * @fr FR-3.9
 *
 * @ac AC-24
 */
class CloseShift
{
    /**
     * @param  array<string, int>  $countedCashPerMethod  Counted amounts in santim per method
     *
     * @throws AuthorizationException
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function execute(
        User $user,
        Shift $shift,
        array $countedCashPerMethod,
        ?string $varianceReason = null,
    ): Shift {
        // 1. Authorisation
        if (! $user->hasPermission('shifts.open')) {
            throw new AuthorizationException(__('shifts.unauthorized_to_close_shift'));
        }

        // Staff can only close their own shift; PM/Owner may close any
        if ($shift->user_id !== $user->id && ! $user->hasRole(Role::PharmacyManager) && ! $user->hasRole(Role::Owner)) {
            throw new AuthorizationException(__('shifts.unauthorized_to_access_other_shift'));
        }

        // 2. Shift must be open (cannot be closed twice)
        if (! $shift->isOpen()) {
            throw new RuntimeException(__('shifts.shift_already_closed'));
        }

        // 3. Compute expected cash per payment method
        $expectedPerMethod = ShiftExpectedCalculator::calculate($shift);

        // 4. Validate counted tenders
        $normalizedCounted = [];
        foreach ($countedCashPerMethod as $method => $amount) {
            $amt = (int) $amount;
            if ($amt < 0) {
                throw new InvalidArgumentException(__('shifts.counted_amount_negative_error'));
            }
            $normalizedCounted[$method] = $amt;
        }

        // Ensure 'cash' is counted
        if (! isset($normalizedCounted['cash'])) {
            $normalizedCounted['cash'] = 0;
        }

        // 5. Compute variance (total counted - total expected)
        $expectedTotal = (int) array_sum($expectedPerMethod);
        $countedTotal = (int) array_sum($normalizedCounted);
        $variance = $countedTotal - $expectedTotal;

        // 6. Variance reason is required when variance is not zero
        if ($variance !== 0 && empty(trim($varianceReason ?? ''))) {
            throw new InvalidArgumentException(__('shifts.variance_reason_required'));
        }

        return DB::transaction(function () use ($user, $shift, $expectedPerMethod, $normalizedCounted, $variance, $varianceReason) {
            /** @var Shift $lockedShift */
            $lockedShift = Shift::where('id', $shift->id)->lockForUpdate()->firstOrFail();

            if (! $lockedShift->isOpen()) {
                throw new RuntimeException(__('shifts.shift_already_closed'));
            }

            $lockedShift->forceFill([
                'status' => 'closed',
                'closed_at' => now(),
                'expected_cash_per_payment_method' => $expectedPerMethod,
                'counted_cash_per_payment_method' => $normalizedCounted,
                'variance' => $variance,
                'variance_reason' => $variance !== 0 ? trim($varianceReason) : null,
            ])->save();

            AuditLogger::record(
                action: 'shift.closed',
                subject: $lockedShift,
                old: ['status' => 'open'],
                new: [
                    'status' => 'closed',
                    'closed_at' => $lockedShift->closed_at->toIso8601String(),
                    'expected' => $expectedPerMethod,
                    'counted' => $normalizedCounted,
                    'variance' => $variance,
                    'variance_reason' => $lockedShift->variance_reason,
                ],
                user: $user,
                description: "Shift #{$lockedShift->id} closed by {$user->username} with variance {$variance} santim"
            );

            return $lockedShift;
        });
    }
}
