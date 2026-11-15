<?php

declare(strict_types=1);

namespace App\Support\Installer;

/**
 * Works out the URL a freshly installed application is reachable at.
 */
final class LocalSite
{
    /**
     * http://<directory>.test when the project sits in a folder that Laravel
     * Herd or Valet serves, otherwise the address of `php artisan serve`.
     */
    public static function defaultUrl(string $projectPath): string
    {
        $parent = self::normalise(dirname($projectPath));

        foreach (self::valetConfigs() as $file) {
            $config = json_decode((string) @file_get_contents($file), true);

            if (! is_array($config)) {
                continue;
            }

            $parked = array_map(self::normalise(...), array_filter((array) ($config['paths'] ?? []), is_string(...)));

            if (in_array($parent, $parked, true)) {
                $tld = is_string($config['tld'] ?? null) ? $config['tld'] : 'test';

                return 'http://'.strtolower(basename($projectPath)).'.'.$tld;
            }
        }

        return 'http://localhost:8000';
    }

    /**
     * @return list<string>
     */
    private static function valetConfigs(): array
    {
        $home = getenv('HOME') ?: getenv('USERPROFILE') ?: '';

        if ($home === '') {
            return [];
        }

        return array_values(array_filter([
            $home.'/Library/Application Support/Herd/config/valet/config.json',
            $home.'/.config/herd/config/valet/config.json',
            $home.'/.config/valet/config.json',
            $home.'/.valet/config.json',
        ], is_file(...)));
    }

    private static function normalise(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }
}
