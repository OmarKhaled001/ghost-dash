<?php

declare(strict_types=1);

namespace App\Support;

use Locale;

/**
 * The languages the application offers, from config/ghostdash.php.
 */
final class Locales
{
    /**
     * @return list<string>
     */
    public static function supported(): array
    {
        $locales = array_filter((array) config('ghostdash.locales'), is_string(...));

        return $locales === [] ? ['en'] : array_values($locales);
    }

    public static function isSupported(?string $locale): bool
    {
        return $locale !== null && in_array($locale, self::supported(), true);
    }

    /**
     * The language named in itself: "English", "العربية".
     */
    public static function nativeName(string $locale): string
    {
        return Locale::getDisplayName($locale, $locale) ?: $locale;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::supported() as $locale) {
            $options[$locale] = self::nativeName($locale);
        }

        return $options;
    }
}
