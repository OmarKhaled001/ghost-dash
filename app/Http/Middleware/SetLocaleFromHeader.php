<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Settings\GeneralSettings;
use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class SetLocaleFromHeader
{
    /**
     * Answer in the language the client asked for with Accept-Language, so
     * that messages and validation errors can be shown to the user as they
     * are. Without the header, the default from General settings applies.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->requested($request) ?? $this->default();

        app()->setLocale($locale);

        $response = $next($request);
        $response->headers->set('Content-Language', $locale);

        return $response;
    }

    private function requested(Request $request): ?string
    {
        if (! $request->headers->has('Accept-Language')) {
            return null;
        }

        // Matches "ar", "ar-EG" and weighted lists such as "fr, ar;q=0.8".
        foreach ($request->getLanguages() as $language) {
            $primary = strtolower(strtok(str_replace('_', '-', $language), '-') ?: '');

            if (Locales::isSupported($primary)) {
                return $primary;
            }
        }

        return null;
    }

    private function default(): string
    {
        try {
            $locale = resolve(GeneralSettings::class)->default_locale;
        } catch (Throwable) {
            // Settings are unreadable (no table yet, database down). The
            // request may still be answerable, and should not die here.
            $locale = null;
        }

        return Locales::isSupported($locale) ? $locale : (string) config('app.locale');
    }
}
