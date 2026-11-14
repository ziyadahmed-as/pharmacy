<?php

namespace App\Console\Commands;

use App\Actions\Reminders\BuildRefillReminders;
use Illuminate\Console\Command;

/**
 * Command to evaluate upcoming prescription refills and queue reminders for consented customers.
 *
 * @uc UC-27
 *
 * @fr FR-5.5
 */
class BuildRefillRemindersCommand extends Command
{
    protected $signature = 'pms:build-refill-reminders {--lookahead=3 : Days in advance to check for due refills}';

    protected $description = 'Build the list of queued refill reminders for consented customers due for a refill (FR-5.5)';

    public function handle(BuildRefillReminders $action): int
    {
        $lookahead = (int) $this->option('lookahead');
        $this->info("Scanning for prescription refills due within {$lookahead} days...");

        $reminders = $action->execute(lookaheadDays: $lookahead);

        $this->info("Queued {$reminders->count()} new refill reminder(s).");

        return self::SUCCESS;
    }
}
