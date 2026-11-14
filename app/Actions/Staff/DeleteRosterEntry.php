<?php

namespace App\Actions\Staff;

use App\Models\RosterEntry;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Gate;

/**
 * Delete a roster entry. Owner and Pharmacy Manager only.
 *
 * @uc UC-40 @fr FR-10.4
 */
class DeleteRosterEntry
{
    public function execute(User $actor, RosterEntry $entry): void
    {
        Gate::forUser($actor)->authorize('staff.manage');

        $old = [
            'staff_id' => $entry->staff_id,
            'date' => $entry->date->toDateString(),
            'start_time' => (string) $entry->start_time,
            'end_time' => (string) $entry->end_time,
            'till_id' => $entry->till_id,
            'note' => $entry->note,
        ];

        AuditLogger::record(
            action: 'roster.deleted',
            subject: $entry,
            old: $old,
            new: [],
            metadata: ['staff_id' => $entry->staff_id, 'date' => $entry->date->toDateString()],
            user: $actor
        );

        $entry->delete();
    }
}
