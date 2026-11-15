<?php

namespace App\Livewire\Staff\Roster;

use App\Actions\Staff\DeleteRosterEntry;
use App\Actions\Staff\SaveRosterEntry;
use App\Models\RosterEntry;
use App\Models\Staff;
use App\Models\Till;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Week and month grid view for staff roster & shifts.
 * Drag-free simple assignment form, on-duty today widget.
 *
 * @uc UC-40 @fr FR-10.4
 */
class Index extends Component
{
    #[Url]
    public string $currentDate = '';

    #[Url]
    public string $viewMode = 'week';

    #[Url]
    public ?int $selectedStaffId = null;

    #[Url]
    public string $activeTab = 'calendar';

    // Modal / Form state
    public bool $showModal = false;

    public ?int $editingId = null;

    public ?int $staff_id = null;

    public string $date = '';

    public string $start_time = '08:00';

    public string $end_time = '16:00';

    public ?int $till_id = null;

    public string $note = '';

    public bool $allow_staff_overlap = false;

    public bool $repeat = false;

    public string $repeat_until = '';

    /** @var array<int> */
    public array $repeat_days = [];

    public function mount(): void
    {
        $user = Auth::user();
        if (! $user->canAccessBusinessData()) {
            abort(403);
        }

        Gate::authorize('viewAny', RosterEntry::class);

        if (empty($this->currentDate)) {
            $this->currentDate = business_date();
        }

        if (! in_array($this->viewMode, ['week', 'month'], true)) {
            $this->viewMode = 'week';
        }
    }

    public function previous(): void
    {
        $carbon = Carbon::parse($this->currentDate);
        if ($this->viewMode === 'month') {
            $this->currentDate = $carbon->subMonth()->toDateString();
        } else {
            $this->currentDate = $carbon->subWeek()->toDateString();
        }
    }

    public function next(): void
    {
        $carbon = Carbon::parse($this->currentDate);
        if ($this->viewMode === 'month') {
            $this->currentDate = $carbon->addMonth()->toDateString();
        } else {
            $this->currentDate = $carbon->addWeek()->toDateString();
        }
    }

    public function today(): void
    {
        $this->currentDate = now()->toDateString();
    }

    public function setViewMode(string $mode): void
    {
        if (in_array($mode, ['week', 'month'], true)) {
            $this->viewMode = $mode;
        }
    }

    public function openCreateModal(?string $date = null): void
    {
        Gate::authorize('create', RosterEntry::class);

        $this->resetValidation();
        $this->editingId = null;
        $this->date = $date ?? $this->currentDate;
        $this->start_time = '08:00';
        $this->end_time = '16:00';
        $this->staff_id = $this->selectedStaffId ?: null;
        $this->till_id = null;
        $this->note = '';
        $this->allow_staff_overlap = false;
        $this->repeat = false;
        $this->repeat_until = '';
        $this->repeat_days = [];
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        Gate::authorize('update', RosterEntry::class);

        $this->resetValidation();
        $entry = RosterEntry::findOrFail($id);

        $this->editingId = $entry->id;
        $this->staff_id = $entry->staff_id;
        $this->date = $entry->date->toDateString();
        $this->start_time = substr((string) $entry->start_time, 0, 5);
        $this->end_time = substr((string) $entry->end_time, 0, 5);
        $this->till_id = $entry->till_id;
        $this->note = (string) $entry->note;
        $this->allow_staff_overlap = false;
        $this->repeat = false;
        $this->repeat_until = '';
        $this->repeat_days = [];
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetValidation();
    }

    public function save(SaveRosterEntry $action): void
    {
        $user = Auth::user();
        $canManage = $user->hasPermission('staff.manage');
        if (! $canManage) {
            abort(403);
        }

        $payload = [
            'staff_id' => $this->staff_id,
            'date' => $this->date,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'till_id' => $this->till_id ?: null,
            'note' => $this->note ?: null,
            'allow_staff_overlap' => $this->allow_staff_overlap,
        ];

        if ($this->editingId) {
            $entry = RosterEntry::findOrFail($this->editingId);
            $action->execute($user, $payload, $entry);
            session()->flash('success', __('roster.messages.updated'));
        } else {
            if ($this->repeat && ! empty($this->repeat_until)) {
                $payload['repeat_until'] = $this->repeat_until;
                $payload['repeat_days'] = ! empty($this->repeat_days) ? array_map('intval', $this->repeat_days) : null;
            }
            $action->execute($user, $payload);
            session()->flash('success', __('roster.messages.saved'));
        }

        $this->closeModal();
    }

    public function delete(int $id, DeleteRosterEntry $action): void
    {
        $user = Auth::user();
        if (! $user->hasPermission('staff.manage')) {
            abort(403);
        }

        $entry = RosterEntry::findOrFail($id);
        $action->execute($user, $entry);
        session()->flash('success', __('roster.messages.deleted'));
    }

    public function render()
    {
        $user = Auth::user();
        if (! $user->canAccessBusinessData()) {
            abort(403);
        }

        Gate::authorize('viewAny', RosterEntry::class);

        $canManage = $user->hasPermission('staff.manage');
        $filterStaffId = $canManage ? $this->selectedStaffId : $user->staff?->id;

        $carbon = Carbon::parse($this->currentDate);

        if ($this->viewMode === 'month') {
            $start = $carbon->copy()->startOfMonth();
            $end = $carbon->copy()->endOfMonth();
            $period = CarbonPeriod::create($start, $end);
            $days = iterator_to_array($period);
            $periodTitle = $carbon->isoFormat('MMMM YYYY');
        } else {
            $start = $carbon->copy()->startOfWeek();
            $end = $carbon->copy()->endOfWeek();
            $period = CarbonPeriod::create($start, $end);
            $days = iterator_to_array($period);
            $periodTitle = sprintf('%s - %s', $start->isoFormat('MMM D'), $end->isoFormat('MMM D, YYYY'));
        }

        $entries = RosterEntry::query()
            ->with(['staff.user', 'till'])
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->when($filterStaffId, fn ($q) => $q->where('staff_id', $filterStaffId))
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        $entriesByDate = $entries->groupBy(fn ($entry) => $entry->date->toDateString());

        $onDutyToday = RosterEntry::query()
            ->with(['staff.user', 'till'])
            ->whereDate('date', today())
            ->orderBy('start_time')
            ->get();

        $activeStaff = $canManage
            ? Staff::query()->where('status', Staff::ACTIVE)->orderBy('name')->get()
            : collect();

        $activeTills = $canManage
            ? Till::query()->where('is_active', true)->orderBy('name')->get()
            : collect();

        return view('livewire.staff.roster.index', [
            'canManage' => $canManage,
            'days' => $days,
            'entriesByDate' => $entriesByDate,
            'onDutyToday' => $onDutyToday,
            'activeStaff' => $activeStaff,
            'activeTills' => $activeTills,
            'periodTitle' => $periodTitle,
            'todayDateStr' => business_date(),
        ])->layout('layouts.app', ['title' => __('roster.title')]);
    }
}
