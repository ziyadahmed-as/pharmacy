<?php

namespace App\Livewire\Staff\Roster;

use App\Models\RosterEntry;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * "On duty today" widget.
 * Shows who is on duty today with shift times and till assignments.
 *
 * @uc UC-40 @fr FR-10.4
 */
class OnDutyToday extends Component
{
    public function render()
    {
        $user = Auth::user();
        if (! $user || ! $user->canAccessBusinessData()) {
            return <<<'HTML'
            <div></div>
            HTML;
        }

        $onDuty = RosterEntry::query()
            ->with(['staff.user', 'till'])
            ->whereDate('date', business_date())
            ->orderBy('start_time')
            ->get();

        return view('livewire.staff.roster.on-duty-today', [
            'onDuty' => $onDuty,
            'canManage' => $user->hasPermission('staff.manage'),
        ]);
    }
}
