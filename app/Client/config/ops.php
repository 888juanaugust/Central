<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Operations: backups, health, alerts
|--------------------------------------------------------------------------
|
| Read by the ops module (app/Client/Modules/OpsModule.php). The backup
| target is a Flysystem disk of config/filesystems.php; the s3 disk reaches
| any S3-compatible bucket (AWS_ENDPOINT for R2 or B2). A local disk is not
| a backup of the machine it sits on, and the health check says so.
|
*/

return [

    'backup' => [
        // 32 random bytes, base64: php artisan central:backup-key. Backups are refused without one.
        'encryption_key' => env('BACKUP_ENCRYPTION_KEY', ''),
        'disk' => env('BACKUP_DISK', 'local'),
        'path' => env('BACKUP_PATH', 'backups'),
        // Only for a local path that is itself a mount of remote storage.
        'local_is_offsite' => (bool) env('BACKUP_LOCAL_IS_OFFSITE', false),
        // The kept uploads a database restore cannot bring back: price-list files, tax filings.
        'include_files' => (bool) env('BACKUP_INCLUDE_FILES', true),
        'files_root' => storage_path('app/private'),
        'keep_daily' => (int) env('BACKUP_KEEP_DAILY', 14),
        'keep_monthly' => (int) env('BACKUP_KEEP_MONTHLY', 12),
        'stale_after_hours' => (int) env('BACKUP_STALE_AFTER_HOURS', 30),
        'pg_dump' => env('BACKUP_PG_DUMP', 'pg_dump'),
        'psql' => env('BACKUP_PSQL', 'psql'),
        'timeout_seconds' => (int) env('BACKUP_TIMEOUT_SECONDS', 3600),
    ],

    'health' => [
        'database_warning_ms' => 250,
        'queue_warning' => 50,
        'queue_critical' => 200,
        'failed_jobs_warning' => 1,
        'failed_jobs_critical' => 10,
        'heartbeat_warning_minutes' => 5,
        'heartbeat_critical_minutes' => 15,
        'backup_warning_hours' => 26,
        'backup_critical_hours' => 50,
        'disk_warning_percent' => 20,
        'disk_critical_percent' => 10,
    ],

    'alert' => [
        // Besides every active administrator.
        'extra_email' => env('OPS_ALERT_EMAIL'),
        'throttle_hours' => 6,
    ],

    // WhatsApp alerts beside the mail. Off until a provider is named; the
    // sender for it is bound in ClientServiceProvider (NullSender until then).
    'whatsapp' => [
        'provider' => env('WHATSAPP_PROVIDER'),
        'token' => env('WHATSAPP_TOKEN'),
        // Comma-separated E.164 numbers: +62812..., +62813...
        'recipients' => env('WHATSAPP_ALERT_TO', ''),
    ],

    // The security watch (central:security-watch, every ten minutes): what
    // counts as a finding in the Activity Log, and how long one is quiet after
    // it has been reported.
    'security' => [
        'window_minutes' => 20,
        'failed_logins_per_ip' => 10,
        'failed_logins_per_account' => 5,
        'known_ip_days' => 90,
        // Local hours; a receipt or invoice deleted outside them is a finding.
        'business_hours' => [7, 19],
        'throttle_hours' => 6,
    ],

];
