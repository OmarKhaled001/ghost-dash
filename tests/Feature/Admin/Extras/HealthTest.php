<?php

declare(strict_types=1);

use App\Filament\Pages\Health;
use App\Health\DiskSpaceCheck;
use App\Services\HealthService;
use Illuminate\Console\Scheduling\Schedule;
use Spatie\Health\Checks\Checks\DebugModeCheck;
use Spatie\Health\Checks\Checks\EnvironmentCheck;
use Spatie\Health\Checks\Checks\OptimizedAppCheck;
use Spatie\Health\Enums\Status;
use Spatie\Health\Facades\Health as HealthFacade;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    actingAsAdmin(adminWith(['view_health']));
});

it('registers the required checks', function (): void {
    $names = HealthFacade::registeredChecks()->map->getName()->all();

    expect($names)->toBe([
        'Database',
        'DatabaseConnectionCount',
        'Cache',
        'Queue',
        'Schedule',
        'DiskSpace',
        'DebugMode',
        'Environment',
        'OptimizedApp',
    ]);
});

it('runs the production-only checks only in production', function (): void {
    $productionOnly = HealthFacade::registeredChecks()
        ->filter(fn ($check): bool => in_array($check::class, [DebugModeCheck::class, EnvironmentCheck::class, OptimizedAppCheck::class], true));

    expect($productionOnly)->toHaveCount(3)
        ->and($productionOnly->every(fn ($check): bool => $check->shouldRun() === false))->toBeTrue();

    app()->detectEnvironment(fn (): string => 'production');

    expect($productionOnly->every(fn ($check): bool => $check->shouldRun() === true))->toBeTrue();
});

it('says so when the checks have never run', function (): void {
    livewire(Health::class)->assertOk()->assertSee(__('admin.health.never_ran'));
});

it('runs the checks on demand and shows translated results', function (): void {
    livewire(Health::class)
        ->callAction('refresh')
        ->assertNotified(__('admin.health.notifications.refreshed'))
        ->assertSee(__('admin.health.checks.database'))
        ->assertSee(__('admin.health.checks.disk_space'))
        ->assertSee(__('admin.health.statuses.ok'));

    $results = resolve(HealthService::class)->latest()->keyBy('name');

    expect($results['Database']['status'])->toBe('ok')
        ->and($results['DebugMode']['status'])->toBe('skipped')
        ->and(resolve(HealthService::class)->lastRanAt())->not->toBeNull();
});

it('shows results in Arabic', function (): void {
    resolve(HealthService::class)->run();
    app()->setLocale('ar');

    livewire(Health::class)->assertSee('الاتصال بقاعدة البيانات');
});

it('measures disk space without shelling out', function (): void {
    $result = DiskSpaceCheck::new()
        ->warnWhenUsedSpaceIsAbovePercentage(100)
        ->failWhenUsedSpaceIsAbovePercentage(100)
        ->run();

    expect($result->status)->toBe(Status::ok())
        ->and($result->meta['disk_space_used_percentage'])->toBeBetween(0, 100);

    $full = DiskSpaceCheck::new()
        ->warnWhenUsedSpaceIsAbovePercentage(-1)
        ->failWhenUsedSpaceIsAbovePercentage(-1)
        ->run();

    expect($full->status)->toBe(Status::failed());
});

it('schedules the heartbeats and the checks every minute', function (string $command): void {
    $event = collect(resolve(Schedule::class)->events())
        ->first(fn ($event): bool => str_contains((string) $event->command, $command));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('* * * * *');
})->with([
    'health:schedule-check-heartbeat',
    'health:queue-check-heartbeat',
    'health:check',
]);
