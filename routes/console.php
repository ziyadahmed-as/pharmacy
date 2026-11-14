<?php

use App\Services\BackupService;
use Illuminate\Support\Facades\Schedule;

Schedule::command('approvals:expire')->hourly();
Schedule::command('staff:licence-alerts')->dailyAt('06:00')->timezone('Africa/Addis_Ababa');
Schedule::command('stock:evaluate-alerts')->dailyAt('07:00')->timezone('Africa/Addis_Ababa');
Schedule::command('batches:quarantine-expired')->dailyAt('00:01')->timezone('Africa/Addis_Ababa');

// UC-37: Backup & Restore Schedules
Schedule::command('backup:run --type=full')->dailyAt('02:00')->timezone('Africa/Addis_Ababa');
Schedule::command('backup:run --type=db')->hourly()->when(fn () => app(BackupService::class)->isWithinOpeningHours());
Schedule::command('backup:clean')->dailyAt('03:00')->timezone('Africa/Addis_Ababa');
Schedule::command('backup:monitor')->hourly();
