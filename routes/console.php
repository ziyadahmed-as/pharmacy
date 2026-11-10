<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('approvals:expire')->hourly();
Schedule::command('staff:licence-alerts')->dailyAt('06:00')->timezone('Africa/Addis_Ababa');
Schedule::command('stock:evaluate-alerts')->dailyAt('07:00')->timezone('Africa/Addis_Ababa');
Schedule::command('batches:quarantine-expired')->dailyAt('00:01')->timezone('Africa/Addis_Ababa');
