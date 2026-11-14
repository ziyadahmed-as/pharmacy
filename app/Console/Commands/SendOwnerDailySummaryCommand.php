<?php

namespace App\Console\Commands;

use App\Actions\Owner\SendDailySummary;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Command to dispatch the end-of-day summary to the business Owner (FR-9.2).
 *
 * @uc UC-32
 *
 * @fr FR-7.7, FR-9.2
 */
class SendOwnerDailySummaryCommand extends Command
{
    protected $signature = 'pms:send-owner-daily-summary {--channel=sms : Delivery channel (sms or email)}';

    protected $description = 'Send daily operational and financial summary to the business Owner (FR-9.2)';

    public function handle(SendDailySummary $action): int
    {
        $channel = (string) $this->option('channel');

        $owners = User::whereHas('roles', fn ($q) => $q->where('slug', Role::Owner->value))->get();

        if ($owners->isEmpty()) {
            $this->warn('No Owner user accounts found.');

            return self::FAILURE;
        }

        foreach ($owners as $owner) {
            $this->info("Dispatching daily summary to Owner {$owner->name}...");
            $result = $action->execute($owner, $channel);

            if ($result['success']) {
                $this->info("Summary successfully sent to {$result['recipient']}.");
            } else {
                $this->error("Failed to send summary to {$result['recipient']}: {$result['error']}");
            }
        }

        return self::SUCCESS;
    }
}
