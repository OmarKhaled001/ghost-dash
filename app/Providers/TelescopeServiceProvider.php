<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\CustomPermission;
use App\Models\Admin;
use App\Support\Telescope\RedactingRequestWatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;
use Laravel\Telescope\Watchers\RequestWatcher;
use Override;

/**
 * Registered by AppServiceProvider, and only when TELESCOPE_ENABLED=true.
 */
final class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->hideSensitiveRequestDetails();

        // Telescope resolves its watchers through the container, so this
        // swaps in the redacting one while config/telescope.php keeps the
        // stock class name, which Telescope's own screens look up there.
        $this->app->bind(RequestWatcher::class, fn (Application $app, array $parameters): RedactingRequestWatcher => new RedactingRequestWatcher($parameters['options'] ?? []));

        // Everything is worth seeing while developing. Anywhere else,
        // Telescope stores only what points at a problem, which keeps both
        // the table size and the amount of user data in it small.
        Telescope::filter(fn (IncomingEntry $entry): bool => app()->environment('local')
            || $entry->isReportableException()
            || $entry->isFailedRequest()
            || $entry->isFailedJob()
            || $entry->isSlowQuery()
            || $entry->hasMonitoredTag());
    }

    /**
     * Telescope's own gate asks $request->user(), which is the default
     * ("web") guard and therefore an end user or nobody, never an admin.
     * The admin guard is asked explicitly instead.
     */
    #[Override]
    protected function authorization(): void
    {
        Telescope::auth(function (Request $request): bool {
            if (app()->environment('local')) {
                return true;
            }

            $admin = Auth::guard('admin')->user();

            return $admin instanceof Admin
                && $admin->is_active
                && $admin->can(CustomPermission::ViewTelescope->value);
        });
    }

    /**
     * Applied in every environment, local included: a password typed into a
     * local login form is still a password, and local databases get shared.
     * These lists only match top-level names; RedactingRequestWatcher is what
     * reaches the nested ones.
     */
    private function hideSensitiveRequestDetails(): void
    {
        Telescope::hideRequestParameters([
            '_token',
            'password',
            'password_confirmation',
            'current_password',
            'new_password',
            'token',
            'secret',
            'code',
            'recovery_code',
        ]);

        Telescope::hideRequestHeaders([
            'authorization',
            'php-auth-pw',
            'cookie',
            'set-cookie',
            'x-csrf-token',
            'x-xsrf-token',
        ]);

        Telescope::hideResponseParameters([
            'token',
            'access_token',
            'refresh_token',
        ]);
    }
}
