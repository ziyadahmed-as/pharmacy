<?php

namespace App\Services;

use App\Models\User;

/**
 * Dispensing needs a valid licence (BR-12, FR-10.3, AGENTS.md rule 11).
 * Licence-dependent actions check the staff licence record, never the role.
 * The category rule comes from setting prescriptions.licence_categories_allowed
 * (empty means any valid licence, OQ-09).
 *
 * Ready for UC-20 (verify) and UC-22 (controlled drugs). Actions are free-form keys
 * such as `prescription.verify`, `prescription.dispense`, `controlled.dispense`.
 *
 * @uc UC-39 @fr FR-10.3 @br BR-12 @ac AC-13
 */
class LicenceGate
{
    public function allows(User $user, string $action): bool
    {
        $staff = $user->staff;

        if (! $staff || ! $staff->isActive() || ! $user->is_active) {
            return false;
        }

        $allowed = array_values(array_filter((array) setting('prescriptions.licence_categories_allowed', [])));
        $today = now()->copy()->timezone(config('pms.timezone'))->toDateString();

        $query = $staff->licences()->whereDate('expires_at', '>=', $today);

        if ($allowed !== []) {
            $lower = array_map('mb_strtolower', $allowed);
            $licences = $query->get()->filter(fn ($l) => in_array(mb_strtolower((string) $l->category), $lower, true));

            return $licences->isNotEmpty();
        }

        return $query->exists();
    }
}
