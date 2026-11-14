<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class MonitorBackups extends Command
{
    protected $signature = 'backup:monitor';

    protected $description = 'Monitor backup staleness (>26h) or failure and trigger alerts';

    public function handle(BackupService $backupService): int
    {
        $this->info('Checking backup staleness and health...');
        $stale = $backupService->checkStaleness();

        if ($stale) {
            $this->warn('Backup staleness or failure detected. Alerts have been dispatched.');
        } else {
            $this->info('Backup status is healthy.');
        }

        return self::SUCCESS;
    }
}
