<?php

namespace App\Actions\Reminders;

use App\Enums\Role as RoleEnum;
use App\Models\Reminder;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Messaging\MessageGateway;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Dispatch an individual refill reminder to a customer via MessageGateway.
 *
 * Rules:
 * - Only authorized pharmacy staff / PM / Owner (SA denied)
 * - Record every send with audit log
 * - On gateway failure, flag status as 'failed' with error message for call/retry (E1)
 *
 * @uc UC-27
 *
 * @fr FR-5.5
 */
class SendRefillReminder
{
    public function __construct(protected MessageGateway $gateway) {}

    public function execute(User $actor, Reminder $reminder): Reminder
    {
        // SA is denied; staff, PM, Owner are permitted
        if ($actor->hasRole(RoleEnum::SystemAdmin) || ! $actor->hasAnyRole([RoleEnum::PharmacyStaff, RoleEnum::PharmacyManager, RoleEnum::Owner])) {
            throw new AuthorizationException(__('reminders.unauthorized'));
        }

        $customer = $reminder->customer;
        $recipientPhone = $customer->phone;

        if (empty($recipientPhone)) {
            $reminder->update([
                'status' => Reminder::STATUS_FAILED,
                'error_message' => __('reminders.customer_has_no_phone'),
                'attempts' => $reminder->attempts + 1,
            ]);

            return $reminder;
        }

        $result = $this->gateway->send($recipientPhone, $reminder->message_text ?? '');

        if ($result['success']) {
            $reminder->update([
                'status' => Reminder::STATUS_SENT,
                'sent_at' => now(),
                'sent_by' => $actor->id,
                'error_message' => null,
                'attempts' => $reminder->attempts + 1,
            ]);

            AuditLogger::record(
                action: 'reminder.sent',
                subject: $reminder,
                old: ['status' => Reminder::STATUS_QUEUED],
                new: ['status' => Reminder::STATUS_SENT, 'sent_at' => $reminder->sent_at],
                metadata: [
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->name,
                    'recipient_phone' => $recipientPhone,
                    'due_date' => $reminder->due_date->toDateString(),
                    'channel' => $reminder->channel,
                ],
                user: $actor,
                description: "Refill reminder sent to {$customer->name} ({$recipientPhone})"
            );
        } else {
            // E1: Flagged for a call or retry
            $errorMessage = $result['error'] ?? 'Delivery failed';

            $reminder->update([
                'status' => Reminder::STATUS_FAILED,
                'error_message' => $errorMessage,
                'attempts' => $reminder->attempts + 1,
            ]);

            AuditLogger::record(
                action: 'reminder.failed',
                subject: $reminder,
                old: ['status' => $reminder->getOriginal('status')],
                new: ['status' => Reminder::STATUS_FAILED, 'error_message' => $errorMessage],
                metadata: [
                    'customer_id' => $customer->id,
                    'recipient_phone' => $recipientPhone,
                    'error' => $errorMessage,
                    'attempts' => $reminder->attempts,
                ],
                user: $actor,
                description: "Refill reminder to {$customer->name} failed: {$errorMessage}"
            );
        }

        return $reminder->refresh();
    }
}
