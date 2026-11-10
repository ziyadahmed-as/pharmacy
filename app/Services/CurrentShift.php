<?php

namespace App\Services;

use App\Exceptions\NoOpenShiftException;
use App\Models\Shift;
use App\Models\Till;
use App\Models\User;

/**
 * Helper used by sell, dispense and refund use cases to require and access
 * the current active shift under the authenticated user on a named till.
 *
 * @uc UC-12
 *
 * @fr FR-3.9, FR-10.4
 *
 * @ac AC-24
 */
final class CurrentShift
{
    /**
     * Get the active open shift for the user, or throw if required.
     *
     * @throws NoOpenShiftException
     */
    public static function forUser(User $user, bool $require = false): ?Shift
    {
        $shift = Shift::with(['till', 'user'])
            ->where('user_id', $user->id)
            ->where('status', 'open')
            ->first();

        if ($require && ! $shift) {
            throw new NoOpenShiftException(__('shifts.no_open_shift_error'));
        }

        return $shift;
    }

    /**
     * Require an active open shift for the user or throw NoOpenShiftException.
     *
     * @throws NoOpenShiftException
     */
    public static function requireForUser(User $user): Shift
    {
        return self::forUser($user, require: true);
    }

    /**
     * Check if the user has an open shift.
     */
    public static function hasOpenShift(User $user): bool
    {
        return Shift::where('user_id', $user->id)
            ->where('status', 'open')
            ->exists();
    }

    /**
     * Get the active open shift on a till, if any.
     */
    public static function forTill(Till|int $till): ?Shift
    {
        $tillId = $till instanceof Till ? $till->id : $till;

        return Shift::with(['till', 'user'])
            ->where('till_id', $tillId)
            ->where('status', 'open')
            ->first();
    }
}
