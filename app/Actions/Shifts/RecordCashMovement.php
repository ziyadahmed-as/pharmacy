<?php

namespace App\Actions\Shifts;

use App\Enums\Role;
use App\Models\Shift;
use App\Models\ShiftCashMovement;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;
use RuntimeException;

/**
 * Record a cash in or cash out movement during an active shift.
 *
 * @uc UC-17 @fr FR-3.9 @ac AC-24
 */
class RecordCashMovement
{
    /**
     * @throws AuthorizationException
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function execute(
        User $user,
        Shift $shift,
        string $type,
        int $amountSantim,
        string $reason,
    ): ShiftCashMovement {
        // 1. Authorisation
        if (! $user->hasPermission('shifts.open')) {
            throw new AuthorizationException(__('shifts.unauthorized_to_record_cash_movement'));
        }

        // Staff can only touch their own shift; PM/Owner can manage any
        if ($shift->user_id !== $user->id && ! $user->hasRole(Role::PharmacyManager) && ! $user->hasRole(Role::Owner)) {
            throw new AuthorizationException(__('shifts.unauthorized_to_access_other_shift'));
        }

        // 2. Shift must be open
        if (! $shift->isOpen()) {
            throw new RuntimeException(__('shifts.shift_already_closed'));
        }

        // 3. Validation
        if (! in_array($type, [ShiftCashMovement::TYPE_CASH_IN, ShiftCashMovement::TYPE_CASH_OUT], true)) {
            throw new InvalidArgumentException(__('shifts.invalid_cash_movement_type'));
        }

        if ($amountSantim <= 0) {
            throw new InvalidArgumentException(__('shifts.amount_must_be_positive'));
        }

        if (trim($reason) === '') {
            throw new InvalidArgumentException(__('shifts.reason_required'));
        }

        $movement = ShiftCashMovement::create([
            'shift_id' => $shift->id,
            'user_id' => $user->id,
            'type' => $type,
            'amount' => $amountSantim,
            'reason' => trim($reason),
        ]);

        AuditLogger::record(
            action: 'shift.cash_movement',
            subject: $shift,
            old: [],
            new: [
                'type' => $type,
                'amount' => $amountSantim,
                'reason' => $reason,
            ],
            user: $user,
            description: "Shift #{$shift->id} recorded {$type} of {$amountSantim} santim by {$user->username}"
        );

        return $movement;
    }
}

