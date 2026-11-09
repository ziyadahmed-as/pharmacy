<?php

namespace App\Actions\Staff;

use App\Models\Staff;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\EndUserSessions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Ends employment: status Ended with an end date. A linked user account is
 * deactivated and its open sessions are ended at once. Staff are never deleted.
 *
 * @uc UC-39 @fr FR-10.2 @ac AC-32
 * Audit: staff.staff.employment_ended, user.account.deactivated
 */
class EndEmployment
{
    public function __construct(private EndUserSessions $endSessions) {}

    public function execute(User $actor, Staff $staff, ?string $endDate = null, ?string $reason = null): Staff
    {
        Gate::forUser($actor)->authorize('staff.manage');

        return DB::transaction(function () use ($actor, $staff, $endDate, $reason) {
            $locked = Staff::whereKey($staff->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === Staff::ENDED) {
                return $locked;
            }

            $old = ['status' => $locked->status, 'end_date' => $locked->end_date?->toDateString()];
            $locked->forceFill([
                'status' => Staff::ENDED,
                'end_date' => $endDate ?: now()->timezone(config('pms.timezone'))->toDateString(),
            ])->save();

            AuditLogger::record('staff.staff.employment_ended', $locked, $old, [
                'status' => Staff::ENDED,
                'end_date' => $locked->end_date?->toDateString(),
            ], ['reason' => $reason], $actor);

            if ($locked->user_id && ($user = User::find($locked->user_id)) && $user->is_active) {
                $user->forceFill(['is_active' => false])->save();
                ($this->endSessions)($user);
                AuditLogger::record('user.account.deactivated', $user, ['is_active' => true], ['is_active' => false], [
                    'reason' => 'employment_ended',
                    'staff_id' => $locked->id,
                ], $actor);
            }

            return $locked->refresh();
        });
    }
}
