<?php

namespace App\Actions\Staff;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Create or edit a staff record. Owner and Pharmacy Manager only (BR-13).
 *
 * @uc UC-39 @fr FR-10.1,FR-10.2 @br BR-13 @ac AC-32
 * Audit: staff.staff.created | staff.staff.updated (id_number masked)
 */
class SaveStaffRecord
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function execute(User $actor, array $data, ?Staff $staff = null): Staff
    {
        Gate::forUser($actor)->authorize('staff.manage');

        $validated = validator($data, [
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
            'id_number' => ['nullable', 'string', 'max:60'],
            'title' => ['nullable', 'string', 'max:100'],
            'hire_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in([Staff::ACTIVE, Staff::ON_LEAVE])],
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:40'],
            'photo_path' => ['nullable', 'string', 'max:255'],
        ])->validate();

        if ($staff && $staff->status === Staff::ENDED) {
            throw ValidationException::withMessages(['status' => __('staff.error.ended_locked')]);
        }

        return DB::transaction(function () use ($validated, $staff) {
            // An empty id_number keeps the stored one (the form never receives it back).
            if ($staff && ($validated['id_number'] ?? null) === null) {
                unset($validated['id_number']);
            }

            if ($staff) {
                $staff->update($validated);

                return $staff->refresh();
            }

            return Staff::create($validated);
        });
    }
}
