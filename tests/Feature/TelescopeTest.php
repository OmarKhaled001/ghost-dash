<?php

declare(strict_types=1);

use App\Models\User;
use App\Providers\TelescopeServiceProvider as AppTelescopeServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\Route;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeServiceProvider;

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/**
 * Boot a fresh application the way a real process would, with the given
 * TELESCOPE_ENABLED value in the environment, so that these tests exercise
 * the real conditional registration instead of registering providers by hand.
 */
function bootWithTelescope(bool $enabled, string $environment = 'staging'): void
{
    $value = $enabled ? 'true' : 'false';

    $_ENV['TELESCOPE_ENABLED'] = $_SERVER['TELESCOPE_ENABLED'] = $value;
    putenv("TELESCOPE_ENABLED={$value}");

    // Telescope keeps its filters in static properties, which outlive the
    // application instance of the previous test.
    Telescope::$filterUsing = [];

    test()->refreshApplication();

    // A rebuilt application is back on the database named in .env.testing
    // with no transaction around it. Under `pest --parallel` that is not this
    // process's database, and whatever a test wrote would stay. These are the
    // two steps of Laravel's own setUp that take care of both.
    ParallelTesting::callSetUpTestCaseCallbacks(test()->target);
    RefreshDatabaseState::$lazilyRefreshed = false;
    test()->refreshDatabase();

    app()->detectEnvironment(fn (): string => $environment);
    config(['auth.timebox_duration' => 0]);
    test()->withoutVite();

    // The tests are about who gets in, not about what gets recorded.
    if ($enabled) {
        Telescope::stopRecording();
    }
}

afterEach(function (): void {
    $_ENV['TELESCOPE_ENABLED'] = $_SERVER['TELESCOPE_ENABLED'] = 'false';
    putenv('TELESCOPE_ENABLED=false');
});

describe('when TELESCOPE_ENABLED is false', function (): void {
    beforeEach(fn () => bootWithTelescope(false));

    it('does not load Telescope at all', function (): void {
        expect(app()->providerIsLoaded(TelescopeServiceProvider::class))->toBeFalse()
            ->and(app()->providerIsLoaded(AppTelescopeServiceProvider::class))->toBeFalse()
            ->and(Route::has('telescope'))->toBeFalse()
            ->and(array_keys(Artisan::all()))->not->toContain('telescope:prune');
    });

    it('answers 404 on the Telescope path', function (): void {
        actingAsAdmin(superAdmin());

        get('/telescope')->assertNotFound();
    });

    it('does not schedule pruning for a command that does not exist', function (): void {
        $commands = collect(resolve(Schedule::class)->events())->map(fn ($event): string => (string) $event->command);

        expect($commands->contains(fn (string $command): bool => str_contains($command, 'telescope:prune')))->toBeFalse();
    });

    it('hides the navigation item even from a super admin', function (): void {
        actingAsAdmin(superAdmin());

        get(panelUrl())->assertOk()->assertDontSee('Telescope');
    });
});

describe('when TELESCOPE_ENABLED is true, outside local', function (): void {
    beforeEach(fn () => bootWithTelescope(true));

    it('loads Telescope', function (): void {
        expect(app()->providerIsLoaded(TelescopeServiceProvider::class))->toBeTrue()
            ->and(app()->providerIsLoaded(AppTelescopeServiceProvider::class))->toBeTrue()
            ->and(Route::has('telescope'))->toBeTrue();
    });

    it('redirects a guest to the panel login', function (): void {
        get('/telescope')->assertRedirect(panelUrl('login'));
    });

    it('answers 403 to a guest calling the Telescope API', function (): void {
        getJson('/telescope/telescope-api/requests')->assertForbidden();
    });

    it('answers 403 to an admin without view_telescope', function (): void {
        actingAsAdmin(adminWith(['view_any:user']));

        get('/telescope')->assertForbidden();
    });

    it('answers 200 to an admin with view_telescope', function (): void {
        actingAsAdmin(adminWith(['view_telescope']));

        get('/telescope')->assertOk();
    });

    it('answers 403 to a deactivated admin who holds the permission', function (): void {
        $admin = adminWith(['view_telescope']);
        $admin->forceFill(['is_active' => false])->save();
        actingAsAdmin($admin);

        get('/telescope')->assertForbidden();
    });

    it('does not accept an end user signed in on the web guard', function (): void {
        // This is the hole the explicit admin-guard wiring closes: Telescope
        // would otherwise evaluate its gate against this user.
        $this->actingAs(User::factory()->create(), 'web');

        get('/telescope')->assertRedirect(panelUrl('login'));
    });

    it('shows the navigation item only with the permission', function (): void {
        actingAsAdmin(adminWith(['view_telescope']));

        get(panelUrl())->assertOk()->assertSee('Telescope');
    });

    it('hides the navigation item without the permission', function (): void {
        actingAsAdmin(adminWith(['view_any:user']));

        get(panelUrl())->assertOk()->assertDontSee('Telescope');
    });

    it('schedules pruning of entries older than 48 hours', function (): void {
        $event = collect(resolve(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains((string) $event->command, 'telescope:prune'));

        expect($event)->not->toBeNull()
            ->and($event->command)->toContain('--hours=48');
    });

    it('hides passwords, tokens, cookies and the Authorization header', function (): void {
        expect(Telescope::$hiddenRequestParameters)->toContain('password', 'password_confirmation', 'current_password', 'token', '_token')
            ->and(Telescope::$hiddenRequestHeaders)->toContain('authorization', 'cookie', 'set-cookie', 'x-csrf-token')
            ->and(Telescope::$hiddenResponseParameters)->toContain('token');
    });

    it('records only problems and tagged entries', function (): void {
        $filter = fn (IncomingEntry $entry): bool => collect(Telescope::$filterUsing)->every(fn (Closure $callback): bool => $callback($entry));

        $ordinaryRequest = IncomingEntry::make(['response_status' => 200])->type('request');
        $failedRequest = IncomingEntry::make(['response_status' => 500])->type('request');
        $fastQuery = IncomingEntry::make(['slow' => false])->type('query');
        $slowQuery = IncomingEntry::make(['slow' => true])->type('query');
        $failedJob = IncomingEntry::make(['status' => 'failed'])->type('job');
        $processedJob = IncomingEntry::make(['status' => 'processed'])->type('job');

        expect($filter($ordinaryRequest))->toBeFalse()
            ->and($filter($fastQuery))->toBeFalse()
            ->and($filter($processedJob))->toBeFalse()
            ->and($filter($failedRequest))->toBeTrue()
            ->and($filter($slowQuery))->toBeTrue()
            ->and($filter($failedJob))->toBeTrue();
    });
});

describe('when TELESCOPE_ENABLED is true, in local', function (): void {
    beforeEach(fn () => bootWithTelescope(true, 'local'));

    it('is open without signing in', function (): void {
        get('/telescope')->assertOk();
    });

    it('records everything', function (): void {
        $filter = fn (IncomingEntry $entry): bool => collect(Telescope::$filterUsing)->every(fn (Closure $callback): bool => $callback($entry));

        expect($filter(IncomingEntry::make(['response_status' => 200])->type('request')))->toBeTrue();
    });

    it('stores neither passwords nor the API token, however deep they sit', function (): void {
        User::factory()->create(['email' => 'nour@example.com', 'password' => 'Correct-Horse-Battery-9']);

        Telescope::startRecording(loadMonitoredTags: false);

        // The API returns its token nested, at data.token. The extra field
        // has the shape of a Livewire update, which SensitiveDataTest shows
        // to be what the panel really posts.
        $token = postJson('/api/v1/auth/login', [
            'email' => 'nour@example.com',
            'password' => 'Correct-Horse-Battery-9',
            'components' => [[
                'snapshot' => json_encode(['data' => ['data' => [['password' => 'Panel-Secret-Passw0rd']]]]),
                'updates' => ['data.password' => 'Panel-Secret-Passw0rd'],
            ]],
        ])->assertOk()->json('data.token');

        Telescope::stopRecording();

        // Entries are written when the request terminates.
        $stored = DB::table('telescope_entries')->where('type', 'request')->pluck('content');

        expect($stored)->toHaveCount(1)
            ->and($stored->first())->toContain('nour@example.com')
            ->not->toContain('Correct-Horse-Battery-9')
            ->not->toContain('Panel-Secret-Passw0rd')
            ->not->toContain((string) $token);
    });
});
