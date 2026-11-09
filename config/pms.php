<?php

/*
| Seed defaults for settings (AGENTS.md rule 8). At runtime, always read through
| setting('group.key'); these values are only the BRD defaults used until the
| settings table has a value (UC-38).
*/
return [
    'timezone' => 'Africa/Addis_Ababa',

    'two_factor_enabled' => env('PMS_TWO_FACTOR', true),

    'defaults' => [
        'security.max_failed_logins' => 5,
        'security.idle_timeout_minutes' => 15,
        'security.password_history' => 5,
        'security.password_min_length' => 8,
        'approvals.expiry_hours' => 72,
        'staff.licence_alert_days' => 60,
        'inventory.near_expiry_alert_days' => [90, 60, 30],
        'inventory.receipt_requires_confirmation' => true,
        'sales.active_payment_methods' => ['cash', 'mobile_money', 'card', 'bank_transfer'],
        'prescriptions.licence_categories_allowed' => [],
        'general.default_language' => 'en',
        'profile.currency' => 'ETB',
    ],

    'bootstrap' => [
        'sa_username' => env('PMS_SA_USERNAME', 'sysadmin'),
        'sa_name' => env('PMS_SA_NAME', 'System Administrator'),
        'sa_password' => env('PMS_SA_PASSWORD'),
        'owner_username' => env('PMS_OWNER_USERNAME', 'owner'),
        'owner_name' => env('PMS_OWNER_NAME', 'Pharmacy Owner'),
        'owner_password' => env('PMS_OWNER_PASSWORD'),
        'pm_username' => env('PMS_PM_USERNAME', 'manager'),
        'pm_name' => env('PMS_PM_NAME', 'Pharmacy Manager'),
        'pm_password' => env('PMS_PM_PASSWORD'),
    ],
];
