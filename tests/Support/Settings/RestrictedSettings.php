<?php

declare(strict_types=1);

namespace Tests\Support\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * A settings group only for the tests of tab permissions.
 */
final class RestrictedSettings extends Settings
{
    public string $value;

    public static function group(): string
    {
        return 'restricted';
    }
}
