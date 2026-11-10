<?php

namespace App\Actions\Shifts;

use App\Exceptions\ShiftAlreadyOpenException;
use App\Exceptions\TillInactiveException;
use App\Exceptions\UserAlreadyHasOpenShiftException;
use App\Models\Shift;
use App\Models\Till;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Open a cash shift on a named till under a named person.
 *
 * @uc UC-12
 *
 * @fr FR-3.9, FR-10.4
 *
 * @br BR-01
 *
 * @ac AC-24
 */
class OpenShift
{
    /**
     * Open a shift for the given user on the given till with opening cash float.
     *
     * @throws AuthorizationException
     * @throws TillInactiveException
     * @throws ShiftAlreadyOpenException
     * @throws UserAlreadyHasOpenShiftException
     * @throws InvalidArgumentException
     */
    public function execute(User $user, Till|int $till, int $openingFloatSantim): Shift
    {
        // Authorisation check (pharmacy_staff, PM, Owner)
        if (! $user->hasPermission('shifts.open')) {
            throw new AuthorizationException(__('shifts.unauthorized_to_open_shift'));
        }

        $tillModel = $till instanceof Till ? $till : Till::findOrFail($till);

        if (! $tillModel->is_active) {
            throw new TillInactiveException(__('shifts.till_inactive_error', ['till' => $tillModel->name]));
        }

        if ($openingFloatSantim < 0) {
            throw new InvalidArgumentException(__('shifts.opening_float_negative_error'));
        }

        return DB::transaction(function () use ($user, $tillModel, $openingFloatSantim) {
            // Lock till row to prevent race conditions
            $lockedTill = Till::where('id', $tillModel->id)->lockForUpdate()->firstOrFail();

            // Refuse if till already has an open shift (E1, AC-24)
            $existingTillShift = Shift::where('till_id', $lockedTill->id)
                ->where('status', 'open')
                ->lockForUpdate()
                ->first();

            if ($existingTillShift) {
                throw new ShiftAlreadyOpenException(__('shifts.till_already_has_open_shift', ['till' => $lockedTill->name]));
            }

            // Refuse if user already has an active open shift
            $existingUserShift = Shift::where('user_id', $user->id)
                ->where('status', 'open')
                ->lockForUpdate()
                ->first();

            if ($existingUserShift) {
                throw new UserAlreadyHasOpenShiftException(__('shifts.user_already_has_open_shift'));
            }

            $shift = Shift::create([
                'till_id' => $lockedTill->id,
                'user_id' => $user->id,
                'opened_at' => now(),
                'opening_float' => $openingFloatSantim,
                'status' => 'open',
            ]);

            // Load relations for audit and caller
            $shift->setRelation('till', $lockedTill);
            $shift->setRelation('user', $user);

            AuditLogger::record(
                action: 'shift.opened',
                subject: $shift,
                old: [],
                new: [
                    'till_id' => $lockedTill->id,
                    'till_name' => $lockedTill->name,
                    'opening_float' => $openingFloatSantim,
                    'opened_at' => $shift->opened_at->toIso8601String(),
                ],
                user: $user,
                description: "Shift #{$shift->id} opened on {$lockedTill->name} by {$user->username}"
            );

            return $shift;
        });
    }
}
