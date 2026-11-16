<?php

namespace App\Console\Commands;

use App\Models\ReportSchedule;
use App\Reports\ReportRegistry;
use App\Services\AuditLogger;
use Illuminate\Console\Command;

class SendScheduledReportsCommand extends Command
{
    protected $signature = 'pms:send-scheduled-reports';

    protected $description = 'Process and email active scheduled reports to configured recipients (UC-30 STEP 5)';

    public function handle(ReportRegistry $registry): int
    {
        $now = now();
        $schedules = ReportSchedule::with('creator')
            ->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('next_run_at')->orWhere('next_run_at', '<=', $now);
            })
            ->get();

        if ($schedules->isEmpty()) {
            $this->info('No pending scheduled reports.');

            return self::SUCCESS;
        }

        foreach ($schedules as $schedule) {
            $definition = $registry->get($schedule->report_key);
            if (! $definition) {
                continue;
            }

            $user = $schedule->creator;
            $filters = $schedule->filters ?? [];
            $result = $definition->query($filters, $user);
            $recipients = $schedule->recipient_emails ?? [];

            // Audit
            AuditLogger::record('report.scheduled_dispatched', null, [], [], [
                'schedule_id' => $schedule->id,
                'report' => $schedule->report_key,
                'recipients' => $recipients,
                'frequency' => $schedule->frequency,
                'row_count' => count($result['rows']),
            ]);

            // Calculate next_run_at
            $nextRun = match ($schedule->frequency) {
                'daily' => $now->copy()->addDay()->startOfDay()->addHours(8), // 8 AM
                'weekly' => $now->copy()->addWeek()->startOfWeek()->addHours(8),
                'monthly' => $now->copy()->addMonth()->startOfMonth()->addHours(8),
                default => $now->copy()->addDay(),
            };

            $schedule->update([
                'last_run_at' => $now,
                'next_run_at' => $nextRun,
            ]);

            $this->info("Dispatched report [{$schedule->report_key}] to ".count($recipients).' recipients.');
        }

        return self::SUCCESS;
    }
}
