<?php

declare(strict_types=1);

use Spatie\Backup\Notifications\Notifiable;
use Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification;
use Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes;

// Without an address there is nobody to mail, so no notification gets a
// channel and none is sent.
$recipient = env('BACKUP_NOTIFICATION_EMAIL');
$channels = filled($recipient) ? ['mail'] : [];

$disk = env('BACKUP_DISK', 'backups');

// The folder backups are written to on the disk. A fixed name on purpose:
// tying it to APP_NAME would start a second, unmonitored folder the day
// someone renames the application.
$name = 'app';

return [

    'backup' => [
        'name' => $name,

        'source' => [
            'files' => [
                // The database dump is added automatically. Files are limited
                // to what users uploaded: everything else is in git.
                'include' => [
                    storage_path('app/public'),
                ],

                'exclude' => [],

                'follow_links' => false,

                'ignore_unreadable_directories' => true,

                'relative_path' => storage_path('app'),
            ],

            'databases' => [
                'mysql',
            ],
        ],

        // The archive is a zip already. A gzip step would need the gzip
        // binary, which Windows servers do not have.
        'database_dump_compressor' => null,

        'database_dump_file_timestamp_format' => null,

        'database_dump_filename_base' => 'database',

        'database_dump_file_extension' => '',

        'destination' => [
            'compression_method' => ZipArchive::CM_DEFAULT,

            'compression_level' => 9,

            'filename_prefix' => '',

            'disks' => [
                $disk,
            ],

            'continue_on_failure' => false,
        ],

        'temporary_directory' => storage_path('app/backup-temp'),

        'password' => env('BACKUP_ARCHIVE_PASSWORD'),

        'encryption' => 'default',

        'verify_backup' => false,

        'tries' => 1,

        'retry_delay' => 0,
    ],

    'notifications' => [
        // "Cleanup succeeded" and "a healthy backup was found" are left out:
        // a daily mail saying nothing is wrong teaches people to ignore the
        // sender, including on the day something is.
        'notifications' => [
            BackupHasFailedNotification::class => $channels,
            UnhealthyBackupWasFoundNotification::class => $channels,
            CleanupHasFailedNotification::class => $channels,
            BackupWasSuccessfulNotification::class => $channels,
        ],

        'notifiable' => Notifiable::class,

        'mail' => [
            // spatie/laravel-backup validates this as an email address even
            // when no notification uses the mail channel, and refuses to run
            // a backup if it is empty. The fallback only satisfies that
            // check: with no channels above, nothing is ever sent to it.
            'to' => filled($recipient) ? $recipient : env('MAIL_FROM_ADDRESS', 'hello@example.com'),

            'from' => [
                'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
                'name' => env('MAIL_FROM_NAME', 'GhostDash'),
            ],
        ],

        'slack' => [
            'webhook_url' => '',
            'channel' => null,
            'username' => null,
            'icon' => null,
        ],

        'discord' => [
            'webhook_url' => '',
            'username' => '',
            'avatar_url' => '',
        ],

        'webhook' => [
            'url' => '',
        ],
    ],

    'log_channel' => null,

    'monitor_backups' => [
        [
            'name' => $name,
            'disks' => [$disk],
            'health_checks' => [
                MaximumAgeInDays::class => 1,
                MaximumStorageInMegabytes::class => 5000,
            ],
        ],
    ],

    'cleanup' => [
        'strategy' => DefaultStrategy::class,

        'default_strategy' => [
            'keep_all_backups_for_days' => 7,
            'keep_daily_backups_for_days' => 16,
            'keep_weekly_backups_for_weeks' => 8,
            'keep_monthly_backups_for_months' => 4,
            'keep_yearly_backups_for_years' => 2,
            'delete_oldest_backups_when_using_more_megabytes_than' => 5000,
        ],

        'tries' => 1,

        'retry_delay' => 0,
    ],

];
