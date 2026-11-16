<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Colors\Color;
use Illuminate\Support\Str;

/**
 * The palettes offered for the panel's primary colour. Named Tailwind
 * palettes rather than a free colour picker: each comes with the eleven
 * shades Filament needs and passes its contrast checks.
 */
enum ThemeColor: string
{
    case Indigo = 'indigo';
    case Blue = 'blue';
    case Sky = 'sky';
    case Cyan = 'cyan';
    case Teal = 'teal';
    case Emerald = 'emerald';
    case Green = 'green';
    case Lime = 'lime';
    case Amber = 'amber';
    case Orange = 'orange';
    case Red = 'red';
    case Rose = 'rose';
    case Pink = 'pink';
    case Fuchsia = 'fuchsia';
    case Purple = 'purple';
    case Violet = 'violet';
    case Slate = 'slate';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $color): array => [$color->value => $color->label()])
            ->all();
    }

    public static function fromSetting(?string $value): self
    {
        return self::tryFrom(Str::lower((string) $value)) ?? self::Indigo;
    }

    /**
     * @return array<int|string, string>
     */
    public function palette(): array
    {
        return Color::{$this->name};
    }

    public function label(): string
    {
        return __('settings/appearance.colors.'.$this->value);
    }
}
