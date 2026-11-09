<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\Staff;
use App\Models\StaffLicence;
use App\Services\Notifier;
use Illuminate\Console\Command;

/**
 * Daily: tell the Pharmacy Manager and Owner when a staff licence is about to expire
 * (within staff.licence_alert_days) and when it has expired. One alert per licence and
 * threshold, however often this runs (FR-9.1).
 *
 * @uc UC-39 @fr FR-9.1,FR-10.3
 */
class StaffLicenceAlerts extends Command
{
    protected $signature = 'staff:licence-alerts';

    protected $description = 'Notify PM and Owner about expiring and expired staff licences';

    public function handle(): int
    {
        $today = now()->timezone(config('pms.timezone'))->startOfDay();
        $days = max(1, (int) setting('staff.licence_alert_days', 60));
        $sent = 0;

        // Only the newest licence of each active staff member matters.
        Staff::query()->where('status', '!=', Staff::ENDED)->with('licences')->each(function (Staff $staff) use ($today, $days, &$sent) {
            /** @var StaffLicence|null $licence */
            $licence = $staff->licences->first();
            if (! $licence) {
                return;
            }

            $expires = $licence->expires_at->copy()->startOfDay();

            if ($expires->lt($today)) {
                $key = "licence-{$licence->id}-expired";
                $params = ['name' => $staff->name, 'date' => $expires->toDateString()];
                $sent += Notifier::toRoles([Role::PharmacyManager, Role::Owner], 'staff.licence.expired', [
                    'message_key' => 'staff.notify.expired', 'message_params' => $params, 'staff_id' => $staff->id,
                ], $key);
            } elseif ($expires->lte($today->copy()->addDays($days))) {
                $key = "licence-{$licence->id}-expiring-{$days}";
                $params = ['name' => $staff->name, 'date' => $expires->toDateString(), 'days' => $today->diffInDays($expires)];
                $sent += Notifier::toRoles([Role::PharmacyManager, Role::Owner], 'staff.licence.expiring', [
                    'message_key' => 'staff.notify.expiring', 'message_params' => $params, 'staff_id' => $staff->id,
                ], $key);
            }
        });

        $this->info("Licence alerts created: {$sent}");

        return self::SUCCESS;
    }
}
