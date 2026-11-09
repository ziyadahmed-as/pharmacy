<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('approvals:expire')->hourly();
Schedule::command('staff:licence-alerts')->dailyAt('06:00')->timezone('Africa/Addis_Ababa');
