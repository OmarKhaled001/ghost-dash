<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Renders initials as an inline SVG. Filament's default provider asks
 * ui-avatars.com for the image, which sends every admin's name to a third
 * party from every browser that opens the panel.
 */
final class InitialsAvatarProvider implements AvatarProvider
{
    public static function for(string $name): string
    {
        $initials = Str::of($name)
            ->trim()
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => Str::upper(Str::substr($part, 0, 1)))
            ->implode('');

        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
            .'<rect width="64" height="64" fill="#4f46e5"/>'
            .'<text x="50%%" y="50%%" dy=".35em" text-anchor="middle" fill="#ffffff" '
            .'font-family="ui-sans-serif,system-ui,sans-serif" font-size="26" font-weight="600">%s</text>'
            .'</svg>',
            e($initials),
        );

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    public function get(Model $record): string
    {
        return self::for(Filament::getNameForDefaultAvatar($record));
    }
}
