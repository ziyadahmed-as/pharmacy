<?php

namespace App\Actions\Reminders;

use App\Models\Prescription;
use App\Models\PrescriptionLine;
use App\Models\Reminder;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Builds queued refill reminders for customers due for a refill.
 *
 * Rules:
 * - Next refill date is due (<= target date)
 * - Only customers with a phone number AND recorded consent (FR-5.5)
 * - Prescription must be valid (not expired, cancelled or rejected)
 * - Line has remaining quantity or unused refills
 * - Message text contains NO health details (medication names, dosage, conditions)
 *
 * @uc UC-27
 *
 * @fr FR-5.5
 */
class BuildRefillReminders
{
    /**
     * @return Collection<int, Reminder>
     */
    public function execute(?CarbonInterface $targetDate = null, int $lookaheadDays = 3): Collection
    {
        $maxDueDate = ($targetDate ?? now())->copy()->addDays($lookaheadDays)->toDateString();
        $pharmacyName = (string) setting('profile.name', config('app.name', 'PMS'));

        $lines = PrescriptionLine::query()
            ->with(['prescription.customer'])
            ->whereNotNull('next_refill_date')
            ->where('next_refill_date', '<=', $maxDueDate)
            ->where(function ($q) {
                $q->where('remaining_quantity', '>', 0)
                    ->orWhereColumn('refills_used', '<', 'refills_allowed');
            })
            ->whereHas('prescription', function ($q) {
                $q->whereNotIn('status', [
                    Prescription::STATUS_CANCELLED,
                    Prescription::STATUS_REJECTED,
                    Prescription::STATUS_EXPIRED,
                ]);
            })
            ->whereHas('prescription.customer', function ($q) {
                $q->where('consent_given', true)
                    ->whereNotNull('phone')
                    ->where('phone', '!=', '');
            })
            ->get();

        $createdReminders = collect();

        foreach ($lines as $line) {
            $customer = $line->prescription->customer;
            $dueDate = $line->next_refill_date->toDateString();

            // Prevent duplicate reminder for the same prescription line & due date
            $exists = Reminder::where('prescription_line_id', $line->id)
                ->whereDate('due_date', $dueDate)
                ->exists();

            if ($exists) {
                continue;
            }

            // Privacy-safe generic message without drug names or health conditions
            $messageText = sprintf(
                'Dear %s, your prescription refill is scheduled for %s at %s. Please visit our pharmacy to collect your refill. Thank you.',
                $customer->name,
                $line->next_refill_date->format('d M Y'),
                $pharmacyName
            );

            $reminder = Reminder::create([
                'customer_id' => $customer->id,
                'prescription_line_id' => $line->id,
                'due_date' => $dueDate,
                'channel' => 'sms',
                'status' => Reminder::STATUS_QUEUED,
                'message_text' => $messageText,
                'attempts' => 0,
            ]);

            $createdReminders->push($reminder);
        }

        return $createdReminders;
    }
}
