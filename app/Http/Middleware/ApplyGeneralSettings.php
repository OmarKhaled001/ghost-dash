<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Settings\GeneralSettings;
use Closure;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class ApplyGeneralSettings
{
    public function __construct(private GeneralSettings $settings) {}

    /**
     * Make the values from the General settings page take effect for this
     * request. Done in middleware rather than a service provider because
     * settings live in the database, and providers also boot for commands
     * that run before a database exists (key:generate, the installer).
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        config([
            'app.name' => $this->settings->app_name,
            // The fallback when the visitor has expressed no preference.
            // The panel language switcher and the API's Accept-Language
            // handling both run after this and may override it.
            'app.locale' => $this->settings->default_locale,
        ]);

        app()->setLocale($this->settings->default_locale);

        // Display only. Timestamps stay in UTC in the database.
        FilamentTimezone::set($this->settings->timezone);

        return $next($request);
    }
}
