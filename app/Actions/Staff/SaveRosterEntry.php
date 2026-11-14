<?php

namespace App\Actions\Staff;

use App\Models\RosterEntry;
use App\Models\Staff;
use App\Models\User;
use App\Services\AuditLogger;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Assign staff to shifts and tills for a day, week, or month.
 * Owner and Pharmacy Manager only.
 *
 * @uc UC-40 @fr FR-10.4
 */
class SaveRosterEntry
{
    /**
     * @param  array<string, mixed>  $data
     * @return RosterEntry|Collection<int, RosterEntry>
     *
     * @throws ValidationException
     */
    public function execute(User $actor, array $data, ?RosterEntry $entry = null): RosterEntry|Collection
    {
        Gate::forUser($actor)->authorize('staff.manage');

        $validated = validator($data, [
            'staff_id' => ['required', 'integer', 'exists:staff,id'],
            'date' => ['required', 'date'],
            'start_time' => ['required', 'string'],
            'end_time' => ['required', 'string'],
            'till_id' => ['nullable', 'integer', 'exists:tills,id'],
            'note' => ['nullable', 'string', 'max:500'],
            'allow_staff_overlap' => ['nullable', 'boolean'],
            'repeat_until' => ['nullable', 'date', 'after_or_equal:date'],
            'repeat_days' => ['nullable', 'array'],
            'repeat_days.*' => ['integer', 'between:1,7'],
        ])->validate();

        $staff = Staff::findOrFail($validated['staff_id']);
        if ($staff->status !== Staff::ACTIVE) {
            $reason = $staff->status === Staff::ENDED ? __('roster.status.ended') : __('roster.status.on_leave');
            throw ValidationException::withMessages([
                'staff_id' => __('roster.error.staff_not_active', ['name' => $staff->name, 'status' => $reason]),
            ]);
        }

        $startTime = Carbon::parse($validated['start_time'])->format('H:i:s');
        $endTime = Carbon::parse($validated['end_time'])->format('H:i:s');

        if ($startTime >= $endTime) {
            throw ValidationException::withMessages([
                'end_time' => __('roster.error.end_time_before_start'),
            ]);
        }

        $allowStaffOverlap = (bool) ($validated['allow_staff_overlap'] ?? false);
        $tillId = $validated['till_id'] ?? null;
        $note = $validated['note'] ?? null;

        return DB::transaction(function () use ($actor, $validated, $entry, $staff, $startTime, $endTime, $tillId, $note, $allowStaffOverlap) {
            // Case 1: Editing existing entry
            if ($entry) {
                $targetDate = Carbon::parse($validated['date'])->toDateString();
                $this->validateConflicts($staff->id, $targetDate, $startTime, $endTime, $tillId, $entry->id, $allowStaffOverlap);

                $old = [
                    'staff_id' => $entry->staff_id,
                    'date' => $entry->date->toDateString(),
                    'start_time' => (string) $entry->start_time,
                    'end_time' => (string) $entry->end_time,
                    'till_id' => $entry->till_id,
                    'note' => $entry->note,
                ];

                $entry->update([
                    'staff_id' => $staff->id,
                    'date' => $targetDate,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'till_id' => $tillId,
                    'note' => $note,
                    'updated_by' => $actor->id,
                ]);

                AuditLogger::record(
                    action: 'roster.updated',
                    subject: $entry,
                    old: $old,
                    new: [
                        'staff_id' => $entry->staff_id,
                        'date' => $entry->date->toDateString(),
                        'start_time' => (string) $entry->start_time,
                        'end_time' => (string) $entry->end_time,
                        'till_id' => $entry->till_id,
                        'note' => $entry->note,
                    ],
                    metadata: ['staff_id' => $entry->staff_id, 'date' => $entry->date->toDateString()],
                    user: $actor
                );

                return $entry->refresh();
            }

            // Case 2: Repeating entries (week/month)
            if (! empty($validated['repeat_until'])) {
                $startDate = Carbon::parse($validated['date']);
                $endDate = Carbon::parse($validated['repeat_until']);
                $period = CarbonPeriod::create($startDate, $endDate);
                $repeatDays = $validated['repeat_days'] ?? null;

                $created = collect();

                foreach ($period as $currentDay) {
                    // Check day of week filter if supplied (ISO-8601 day: 1 for Monday through 7 for Sunday)
                    if ($repeatDays !== null && ! in_array($currentDay->dayOfWeekIso, $repeatDays, true)) {
                        continue;
                    }

                    $dateStr = $currentDay->toDateString();
                    $this->validateConflicts($staff->id, $dateStr, $startTime, $endTime, $tillId, null, $allowStaffOverlap);

                    $createdEntry = RosterEntry::create([
                        'staff_id' => $staff->id,
                        'date' => $dateStr,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'till_id' => $tillId,
                        'note' => $note,
                        'created_by' => $actor->id,
                    ]);

                    AuditLogger::record(
                        action: 'roster.saved',
                        subject: $createdEntry,
                        old: [],
                        new: [
                            'staff_id' => $createdEntry->staff_id,
                            'date' => $createdEntry->date->toDateString(),
                            'start_time' => (string) $createdEntry->start_time,
                            'end_time' => (string) $createdEntry->end_time,
                            'till_id' => $createdEntry->till_id,
                            'note' => $createdEntry->note,
                        ],
                        metadata: ['published' => true, 'staff_id' => $createdEntry->staff_id, 'date' => $createdEntry->date->toDateString()],
                        user: $actor
                    );

                    $created->push($createdEntry);
                }

                return $created;
            }

            // Case 3: Single entry creation
            $dateStr = Carbon::parse($validated['date'])->toDateString();
            $this->validateConflicts($staff->id, $dateStr, $startTime, $endTime, $tillId, null, $allowStaffOverlap);

            $newEntry = RosterEntry::create([
                'staff_id' => $staff->id,
                'date' => $dateStr,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'till_id' => $tillId,
                'note' => $note,
                'created_by' => $actor->id,
            ]);

            AuditLogger::record(
                action: 'roster.saved',
                subject: $newEntry,
                old: [],
                new: [
                    'staff_id' => $newEntry->staff_id,
                    'date' => $newEntry->date->toDateString(),
                    'start_time' => (string) $newEntry->start_time,
                    'end_time' => (string) $newEntry->end_time,
                    'till_id' => $newEntry->till_id,
                    'note' => $newEntry->note,
                ],
                metadata: ['published' => true, 'staff_id' => $newEntry->staff_id, 'date' => $newEntry->date->toDateString()],
                user: $actor
            );

            return $newEntry;
        });
    }

    /**
     * Validate till conflicts (hard refusal E1) and staff conflicts (warning).
     *
     * @throws ValidationException
     */
    protected function validateConflicts(
        int $staffId,
        string $date,
        string $startTime,
        string $endTime,
        ?int $tillId,
        ?int $excludeId,
        bool $allowStaffOverlap
    ): void {
        // E1: Refuse a second cash shift on the same till at overlapping times
        if ($tillId !== null) {
            $overlappingTill = RosterEntry::query()
                ->whereDate('date', $date)
                ->where('till_id', $tillId)
                ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                ->where(function ($q) use ($startTime, $endTime) {
                    $q->where('start_time', '<', $endTime)
                        ->where('end_time', '>', $startTime);
                })
                ->exists();

            if ($overlappingTill) {
                throw ValidationException::withMessages([
                    'till_id' => __('roster.error.overlapping_till'),
                ]);
            }
        }

        // Warn on a staff member scheduled twice at once
        $overlappingStaff = RosterEntry::query()
            ->whereDate('date', $date)
            ->where('staff_id', $staffId)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->where(function ($q) use ($startTime, $endTime) {
                $q->where('start_time', '<', $endTime)
                    ->where('end_time', '>', $startTime);
            })
            ->exists();

        if ($overlappingStaff && ! $allowStaffOverlap) {
            throw ValidationException::withMessages([
                'staff_id' => __('roster.warning_overlapping_staff'),
            ]);
        }
    }
}
