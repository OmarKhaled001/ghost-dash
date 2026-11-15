<?php

declare(strict_types=1);

namespace App\Providers;

use App\Health\DiskSpaceCheck;
use App\Models\Admin;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Activitylog\Support\CauserResolver;
use Spatie\Health\Checks\Checks\CacheCheck;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Checks\DatabaseConnectionCountCheck;
use Spatie\Health\Checks\Checks\DebugModeCheck;
use Spatie\Health\Checks\Checks\EnvironmentCheck;
use Spatie\Health\Checks\Checks\OptimizedAppCheck;
use Spatie\Health\Checks\Checks\QueueCheck;
use Spatie\Health\Checks\Checks\ScheduleCheck;
use Spatie\Health\Facades\Health;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->configureActivityLogCauser();
        $this->registerTelescope();
    }

    public function boot(): void
    {
        $this->configureModels();
        $this->configureDatabase();
        $this->configureDates();
        $this->configureUrls();
        $this->configurePasswords();
        $this->configureHealthChecks();
    }

    /**
     * The activity log asks the default guard who is acting, which is "web"
     * and therefore never an admin. Ask each guard in turn instead: a panel
     * request has an admin session, an API request has a Sanctum token.
     *
     * Registered as a resolving callback because the resolver is rebuilt for
     * every request under Octane and would lose a one-off configuration.
     */
    private function configureActivityLogCauser(): void
    {
        $this->app->resolving(CauserResolver::class, function (CauserResolver $resolver): void {
            $resolver->resolveUsing(function (Model|int|string|null $explicit): ?Model {
                // causedBy($model) passes through here too and must win.
                if ($explicit instanceof Model) {
                    return $explicit;
                }

                foreach (['admin', 'sanctum', 'web'] as $guard) {
                    $user = Auth::guard($guard)->user();

                    if ($user instanceof Model) {
                        return $user;
                    }
                }

                return null;
            });
        });
    }

    /**
     * Telescope is kept out of package discovery (composer.json) so that it
     * costs nothing when it is switched off: no providers, no routes, no
     * watchers, no commands. That is what makes it reasonable to ship in
     * "require" and switch on in production only while debugging.
     */
    private function registerTelescope(): void
    {
        if (! config('telescope.enabled')) {
            return;
        }

        $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
        $this->app->register(TelescopeServiceProvider::class);
    }

    private function configureModels(): void
    {
        // Lazy loading, silently dropped attributes and reads of attributes
        // that were never selected are bugs we want to hear about while
        // developing. In production they are logged-and-tolerated instead
        // of taking a page down.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Polymorphic columns (activity log, role assignments, API tokens)
        // store these aliases instead of class names, so moving or renaming
        // a model class later does not orphan existing rows.
        Relation::morphMap([
            'admin' => Admin::class,
            'user' => User::class,
            'role' => Role::class,
            'permission' => Permission::class,
        ]);
    }

    private function configureDatabase(): void
    {
        // migrate:fresh, migrate:refresh, db:wipe and friends.
        DB::prohibitDestructiveCommands($this->app->isProduction());
    }

    private function configureDates(): void
    {
        Date::use(CarbonImmutable::class);
    }

    private function configureUrls(): void
    {
        // Behind a TLS-terminating proxy the request looks like plain HTTP,
        // which would otherwise leak http:// asset and redirect URLs.
        if ($this->app->isProduction()) {
            URL::forceHttps();
        }
    }

    private function configureHealthChecks(): void
    {
        // Debug mode, environment and config caching are only wrong on a
        // production server. On a laptop they would be red forever, and a
        // page that is always red stops being read.
        $inProduction = fn (): bool => $this->app->isProduction();

        Health::checks([
            DatabaseCheck::new(),
            DatabaseConnectionCountCheck::new()
                ->warnWhenMoreConnectionsThan(50)
                ->failWhenMoreConnectionsThan(100),
            CacheCheck::new(),
            QueueCheck::new(),
            ScheduleCheck::new(),
            DiskSpaceCheck::new()
                ->warnWhenUsedSpaceIsAbovePercentage(70)
                ->failWhenUsedSpaceIsAbovePercentage(90),
            DebugModeCheck::new()->if($inProduction),
            EnvironmentCheck::new()->if($inProduction),
            OptimizedAppCheck::new()->if($inProduction),
        ]);
    }

    private function configurePasswords(): void
    {
        Password::defaults(function (): Password {
            $rule = Password::min(12)->mixedCase()->numbers();

            // The breach lookup calls an external API, so it stays out of
            // local and test runs where it would be slow and flaky.
            return $this->app->isProduction() ? $rule->uncompromised() : $rule;
        });
    }
}
