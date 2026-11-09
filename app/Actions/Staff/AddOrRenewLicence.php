<?php

namespace App\Actions\Staff;

use App\Models\Staff;
use App\Models\StaffLicence;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Add a licence or record a renewal. Old rows are kept as history.
 *
 * @uc UC-39 @fr FR-10.3 @br BR-12 @ac AC-13
 * Audit: staff.staff_licence.created
 */
class AddOrRenewLicence
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function execute(User $actor, Staff $staff, array $data): StaffLicence
    {
        Gate::forUser($actor)->authorize('staff.manage');

        $validated = validator($data, [
            'number' => ['required', 'string', 'max:100'],
            'issuing_body' => ['required', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:100'],
            'issued_at' => ['nullable', 'date'],
            'expires_at' => ['required', 'date', 'after_or_equal:issued_at'],
        ])->validate();

        return $staff->licences()->create($validated);
    }
}
