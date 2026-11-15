<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;
use Spatie\Health\Models\HealthCheckResultHistoryItem;

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
|
| Every scheduled task of the kit is declared in this file so there is one
| place to look. The table in README.md mirrors it.
|
*/

// Health. The two heartbeats are what the Schedule and Queue checks (and
// dash:doctor) look for: one proves this scheduler runs, the other pushes a
// job that only a live queue worker can pick up.
Schedule::command('health:schedule-check-heartbeat')->everyMinute();
Schedule::command('health:queue-check-heartbeat')->everyMinute();
Schedule::command('health:check')->everyMinute();

// Housekeeping for authentication tables.
Schedule::command('sanctum:prune-expired --hours=24')->dailyAt('00:30');
Schedule::command('auth:clear-resets users')->dailyAt('00:45');
Schedule::command('auth:clear-resets admins')->dailyAt('00:45');

// Backups: remove what the retention policy no longer keeps, take a new
// one, then check that a recent, sane backup really exists.
Schedule::command('backup:clean')->dailyAt('01:00');
Schedule::command('backup:run')->dailyAt('01:30');
Schedule::command('backup:monitor')->dailyAt('03:00');

// Deletes activity older than ACTIVITYLOG_RETENTION_DAYS. --force lets it
// run in production, where the command otherwise asks for confirmation.
Schedule::command('activitylog:clean --force --days='.config('activitylog.clean_after_days'))
    ->dailyAt('02:00');

// The command only exists while Telescope is loaded.
if (config('telescope.enabled')) {
    Schedule::command('telescope:prune --hours=48')->dailyAt('02:15');
}

// Health results older than the history window in config/health.php.
Schedule::command('model:prune', ['--model' => [HealthCheckResultHistoryItem::class]])->dailyAt('02:30');
