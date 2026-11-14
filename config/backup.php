<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Backup Disk
    |--------------------------------------------------------------------------
    | The filesystem disk where backup archives are stored.
    */
    'disk' => env('BACKUP_DISK', 'backup'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    | Key used to encrypt backup archives with AES-256-CBC.
    | MUST ONLY live in server environment; never visible to SA (FR-8.10, AC-35).
    */
    'encryption_key' => env('BACKUP_ENCRYPTION_KEY', env('APP_KEY')),

    /*
    |--------------------------------------------------------------------------
    | Retention Policy (BRD NFR-4)
    |--------------------------------------------------------------------------
    | Days of daily backups to keep and month-end snapshots to preserve.
    */
    'retention_daily' => (int) env('BACKUP_RETENTION_DAILY', 30),
    'retention_month_end' => (int) env('BACKUP_RETENTION_MONTH_END', 12),

    /*
    |--------------------------------------------------------------------------
    | Opening Hours
    |--------------------------------------------------------------------------
    | Window during which hourly database backups run.
    */
    'opening_hours' => env('BACKUP_OPENING_HOURS', '08:00-20:00'),
];
