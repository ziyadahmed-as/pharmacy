<?php

namespace App\Livewire\Staff\Attendance;

use App\Actions\Staff\Attendance\ClockIn;
use App\Actions\Staff\Attendance\ClockOut;
use App\Models\Attendance;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Attendance clock-in / clock-out panel.
 *
 * Staff see only their own record.  PM and Owner see all staff with filters.
 *
 * @uc UC-41 @fr FR-10.5
 */
class Index extends Component
{
    #[Url]
    public string $filterMonth = '';

    #[Url]
    public ?int $filterStaffId = null;

    public function mount(): void
    {
        $user = Auth::user();

        if (! $user->canAccessBusinessData()) {
            abort(403);
        }

        if (! $user->hasPermission('attendance.manage') &&
            ! $user->hasPermission('attendance.view') &&
            ! $user->hasPermission('attendance.view.own')) {
            abort(403);
        }

        if (empty($this->filterMonth)) {
            $this->filterMonth = now()->format('Y-m');
        }
    }

    public function clockIn(ClockIn $action): void
    {
        $user = Auth::user();

        if (! $user->hasPermission('attendance.manage')) {
            abort(403);
        }

        $staff = $user->staff;

        if (! $staff) {
            session()->flash('error', 'No staff record linked to your account.');

            return;
        }

        $action->execute($staff, $user, now());
        session()->flash('success', __('attendance.clocked_in_success'));
    }

    public function clockOut(ClockOut $action): void
    {
        $user = Auth::user();

        if (! $user->hasPermission('attendance.manage')) {
            abort(403);
        }

        $staff = $user->staff;

        if (! $staff) {
            session()->flash('error', 'No staff record linked to your account.');

            return;
        }

        $action->execute($staff, $user, now());
        session()->flash('success', __('attendance.clocked_out_success'));
    }

    public function render()
    {
        $user = Auth::user();
        $canViewAll = $user->hasPermission('attendance.view') || $user->hasPermission('leave.manage');
        $ownStaff = $user->staff;

        // Build query
        $monthStart = Carbon::parse($this->filterMonth.'-01')->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $query = Attendance::query()
            ->with('staff.user')
            ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->orderByDesc('date');

        if ($canViewAll) {
            if ($this->filterStaffId) {
                $query->where('staff_id', $this->filterStaffId);
            }
        } else {
            // Staff can only see own records (BR-13)
            if ($ownStaff) {
                $query->where('staff_id', $ownStaff->id);
            } else {
                $query->whereRaw('1 = 0'); // no records
            }
        }

        $records = $query->get();

        // Today's record for the acting user
        $todayRecord = $ownStaff
            ? Attendance::query()
                ->where('staff_id', $ownStaff->id)
                ->whereDate('date', business_date())
                ->first()
            : null;

        $allStaff = $canViewAll
            ? Staff::query()->where('status', Staff::ACTIVE)->orderBy('name')->get()
            : collect();

        return view('livewire.staff.attendance.index', [
            'records' => $records,
            'todayRecord' => $todayRecord,
            'ownStaff' => $ownStaff,
            'allStaff' => $allStaff,
            'canViewAll' => $canViewAll,
        ])->layout('layouts.app', ['title' => __('attendance.title')]);
    }
}
