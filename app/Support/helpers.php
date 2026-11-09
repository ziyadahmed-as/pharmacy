<?php

use App\Services\SettingsRepository;
use Carbon\CarbonInterface;

if (! function_exists('setting')) {
    /** Typed, cached setting lookup: setting('security.max_failed_logins'). */
    function setting(string $key, mixed $default = null): mixed
    {
        return app(SettingsRepository::class)->get($key, $default);
    }
}

if (! function_exists('local_time')) {
    /** Times are stored in UTC and displayed in Africa/Addis_Ababa (AGENTS.md section 4). */
    function local_time(?CarbonInterface $time, string $format = 'Y-m-d H:i'): string
    {
        return $time ? $time->copy()->timezone(config('pms.timezone', 'Africa/Addis_Ababa'))->format($format) : '—';
    }
}
