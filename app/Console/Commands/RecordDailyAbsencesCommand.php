<?php

namespace App\Console\Commands;

use App\Actions\Staff\Attendance\RecordDailyAbsences;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Console command to flag absences for scheduled staff who did not clock in (UC-41).
 *
 * @uc UC-41 @fr FR-10.5
 */
class RecordDailyAbsencesCommand extends Command
{
    protected $signature = 'pms:record-daily-absences {--date= : The date to evaluate (YYYY-MM-DD, defaults to today)}';

    protected $description = 'Evaluate scheduled roster entries and record absences for staff who did not clock in (UC-41)';

    public function handle(RecordDailyAbsences $action): int
    {
        $dateStr = $this->option('date');
        $date = $dateStr ? Carbon::parse($dateStr) : now();

        $this->info("Evaluating staff attendance and absences for {$date->toDateString()}...");

        $result = $action->execute($date);

        $this->info("Scheduled: {$result['total_scheduled']} | Attended: {$result['attended']} | Absent: {$result['absences_recorded']} | On Leave: {$result['on_leave']}");

        return self::SUCCESS;
    }
}
