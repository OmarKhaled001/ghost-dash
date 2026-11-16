<?php

declare(strict_types=1);

namespace App\Settings;

use App\Settings\Contracts\PublicSettings;
use Spatie\LaravelSettings\Settings;

final class SocialSettings extends Settings implements PublicSettings
{
    public ?string $facebook = null;

    public ?string $instagram = null;

    public ?string $x = null;

    public ?string $linkedin = null;

    public ?string $youtube = null;

    public ?string $tiktok = null;

    public ?string $snapchat = null;

    public static function group(): string
    {
        return 'social';
    }

    /**
     * Only the networks that are filled in, so a footer can loop over them.
     */
    public function toPublicArray(): array
    {
        return array_filter($this->toArray(), filled(...));
    }
}
