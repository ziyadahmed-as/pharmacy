<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class CleanBackups extends Command
{
    protected $signature = 'backup:clean';

    protected $description = 'Clean old backups according to daily (30) and month-end (12) retention policies';

    public function handle(BackupService $backupService): int
    {
        $this->info('Evaluating backup archives for retention cleanup...');
        $deleted = $backupService->clean();

        $count = count($deleted);
        $this->info("Cleanup completed. Deleted {$count} expired backup archive(s).");

        return self::SUCCESS;
    }
}
