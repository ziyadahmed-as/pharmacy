<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Failed Login Lock Threshold
    |--------------------------------------------------------------------------
    | The number of consecutive failed login attempts before an account
    | is locked automatically. Unlocking requires a System Administrator (UC-36).
    */
    'failed_login_threshold' => (int) env('AUTH_FAILED_LOGIN_THRESHOLD', 5),

    /*
    |--------------------------------------------------------------------------
    | Session Inactivity Timeout (Minutes)
    |--------------------------------------------------------------------------
    | Automatic logout occurs after this period of inactivity (default: 15 mins).
    */
    'session_inactivity_timeout' => (int) env('AUTH_SESSION_INACTIVITY_TIMEOUT', 15),

    /*
    |--------------------------------------------------------------------------
    | Two-Factor Authentication
    |--------------------------------------------------------------------------
    | Master toggle for Two-Factor Authentication capabilities in the pharmacy.
    */
    'two_factor_enabled' => (bool) env('AUTH_TWO_FACTOR_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | MFA Roles
    |--------------------------------------------------------------------------
    | System roles that support MFA enforcement.
    */
    'mfa_supported_roles' => [
        'pharmacy_manager',
        'system_administrator',
    ],
];
