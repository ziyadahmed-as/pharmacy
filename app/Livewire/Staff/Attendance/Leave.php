<?php

namespace App\Livewire\Staff\Attendance;

use App\Actions\Staff\Leave\SubmitLeaveRequest;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Leave request form and list.
 *
 * Staff can submit and view own requests.  PM / Owner see all and can see
 * the over-balance flag.
 *
 * @uc UC-41 @fr FR-10.6
 */
class Leave extends Component
{
    public bool $showForm = false;

    public ?int $leaveTypeId = null;

    public string $startDate = '';

    public string $endDate = '';

    public string $reason = '';

    #[Url]
    public string $filterStatus = '';

    #[Url]
    public ?int $filterStaffId = null;

    public function mount(): void
    {
        $user = Auth::user();

        if (! $user->canAccessBusinessData()) {
            abort(403);
        }

        if (! $user->hasPermission('leave.request') &&
            ! $user->hasPermission('leave.manage')) {
            abort(403);
        }

        $this->startDate = business_date();
        $this->endDate = business_date();
    }

    public function openForm(): void
    {
        if (! Auth::user()->hasPermission('leave.request')) {
            abort(403);
        }

        $this->reset(['leaveTypeId', 'startDate', 'endDate', 'reason']);
        $this->startDate = business_date();
        $this->endDate = business_date();
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetValidation();
    }

    public function daysRequested(): int
    {
        if (empty($this->startDate) || empty($this->endDate)) {
            return 0;
        }

        try {
            $start = Carbon::parse($this->startDate);
            $end = Carbon::parse($this->endDate);

            if ($end->lt($start)) {
                return 0;
            }

            return (int) $start->diffInDays($end) + 1;
        } catch (\Throwable) {
            return 0;
        }
    }

    public function submit(SubmitLeaveRequest $action): void
    {
        $user = Auth::user();

        if (! $user->hasPermission('leave.request')) {
            abort(403);
        }

        $this->validate([
            'leaveTypeId' => 'required|exists:leave_types,id',
            'startDate' => 'required|date',
            'endDate' => 'required|date|after_or_equal:startDate',
            'reason' => 'nullable|string|max:500',
        ]);

        $staff = $user->staff;

        if (! $staff) {
            session()->flash('error', 'No staff record linked to your account.');

            return;
        }

        $leaveType = LeaveType::findOrFail((int) $this->leaveTypeId);

        $action->execute(
            staff: $staff,
            actor: $user,
            leaveType: $leaveType,
            startDate: $this->startDate,
            endDate: $this->endDate,
            reason: $this->reason ?: null,
        );

        session()->flash('success', __('attendance.leave_submitted'));
        $this->showForm = false;
        $this->resetValidation();
    }

    public function cancel(int $id): void
    {
        $user = Auth::user();
        $request = LeaveRequest::findOrFail($id);

        // Staff can cancel own pending; PM/Owner can cancel any pending
        $ownStaff = $user->staff;
        $isOwn = $ownStaff && $request->staff_id === $ownStaff->id;
        $canAll = $user->hasPermission('leave.manage');

        if (! $isOwn && ! $canAll) {
            abort(403);
        }

        if ($request->status !== 'pending') {
            session()->flash('error', 'Only pending requests can be cancelled.');

            return;
        }

        $request->update(['status' => 'cancelled']);
        session()->flash('success', __('attendance.leave_cancelled'));
    }

    public function render()
    {
        $user = Auth::user();
        $ownStaff = $user->staff;
        $canAll = $user->hasPermission('leave.manage');

        $query = LeaveRequest::query()
            ->with(['staff.user', 'leaveType'])
            ->latest('created_at');

        if ($canAll) {
            if ($this->filterStaffId) {
                $query->where('staff_id', $this->filterStaffId);
            }

            if ($this->filterStatus) {
                $query->where('status', $this->filterStatus);
            }
        } else {
            // Staff see own only (BR-13)
            if ($ownStaff) {
                $query->where('staff_id', $ownStaff->id);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $requests = $query->get();
        $leaveTypes = LeaveType::query()->orderBy('name')->get();

        return view('livewire.staff.attendance.leave', [
            'requests' => $requests,
            'leaveTypes' => $leaveTypes,
            'canAll' => $canAll,
            'ownStaff' => $ownStaff,
            'daysRequested' => $this->daysRequested(),
        ])->layout('layouts.app', ['title' => __('attendance.leave_title')]);
    }
}
