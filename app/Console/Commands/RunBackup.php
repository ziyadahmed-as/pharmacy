<?php

namespace App\Console\Commands;

use App\Models\BackupRun;
use App\Services\BackupService;
use Illuminate\Console\Command;
use Throwable;

class RunBackup extends Command
{
    protected $signature = 'backup:run {--type=full : Backup type (full or db)}';

    protected $description = 'Create an encrypted system backup archive (database and uploaded files)';

    public function handle(BackupService $backupService): int
    {
        $type = in_array($this->option('type'), [BackupRun::TYPE_DB, BackupRun::TYPE_FULL], true)
            ? $this->option('type')
            : BackupRun::TYPE_FULL;

        $this->info("Starting {$type} backup...");

        try {
            $run = $backupService->createBackup($type);
            $this->info("Backup created successfully: {$run->filename} ({$run->formattedSize()})");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Backup failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
