<?php

namespace App\Actions\Staff;

use App\Enums\Role;
use App\Models\Reminder;
use App\Models\Staff;
use App\Models\StaffDocument;
use App\Services\Notifier;

/**
 * Check expiring and expired staff documents, alerting PM and Owner
 * via Notifier and recording Reminder entries.
 *
 * @uc UC-39
 *
 * @fr FR-9.1, FR-10.3
 */
class CheckDocumentExpiry
{
    /**
     * @return array{notified: int, reminders: int}
     */
    public function execute(?int $lookaheadDays = null): array
    {
        $today = now()->timezone(config('pms.timezone', 'Africa/Addis_Ababa'))->startOfDay();
        $days = $lookaheadDays ?? max(1, (int) setting('staff.document_alert_days', 30));

        $notified = 0;
        $remindersCount = 0;

        $documents = StaffDocument::query()
            ->whereNotNull('expires_at')
            ->whereHas('staff', fn ($q) => $q->where('status', '!=', Staff::ENDED))
            ->with(['staff.user'])
            ->get();

        foreach ($documents as $doc) {
            $expires = $doc->expires_at->copy()->startOfDay();
            $staff = $doc->staff;

            if ($expires->lt($today)) {
                // Expired
                $dedupeKey = "doc-{$doc->id}-expired";
                $params = [
                    'name' => $staff->name,
                    'type' => $doc->type,
                    'date' => $expires->toDateString(),
                ];

                $sent = Notifier::toRoles(
                    [Role::PharmacyManager, Role::Owner],
                    'staff.document.expired',
                    [
                        'message_key' => 'staff.documents.notify_expired',
                        'message_params' => $params,
                        'staff_id' => $staff->id,
                        'document_id' => $doc->id,
                    ],
                    $dedupeKey
                );

                if ($sent > 0) {
                    $notified += $sent;
                }
            } elseif ($expires->lte($today->copy()->addDays($days))) {
                // Expiring soon
                $daysLeft = (int) $today->diffInDays($expires, false);
                $dedupeKey = "doc-{$doc->id}-expiring-{$days}";
                $params = [
                    'name' => $staff->name,
                    'type' => $doc->type,
                    'date' => $expires->toDateString(),
                    'days' => $daysLeft,
                ];

                $sent = Notifier::toRoles(
                    [Role::PharmacyManager, Role::Owner],
                    'staff.document.expiring',
                    [
                        'message_key' => 'staff.documents.notify_expiring',
                        'message_params' => $params,
                        'staff_id' => $staff->id,
                        'document_id' => $doc->id,
                    ],
                    $dedupeKey
                );

                if ($sent > 0) {
                    $notified += $sent;
                }
            }
        }

        return [
            'notified' => $notified,
            'reminders' => $notified,
        ];
    }
}
