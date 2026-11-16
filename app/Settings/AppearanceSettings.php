<?php

declare(strict_types=1);

namespace App\Settings;

use App\Enums\ThemeColor;
use App\Settings\Contracts\PublicSettings;
use Spatie\LaravelSettings\Settings;

final class AppearanceSettings extends Settings implements PublicSettings
{
    public string $primary_color;

    public static function group(): string
    {
        return 'appearance';
    }

    public function primaryColor(): ThemeColor
    {
        return ThemeColor::fromSetting($this->primary_color);
    }

    public function toPublicArray(): array
    {
        return [
            'primary_color' => $this->primaryColor()->value,
            // The 500 shade as a CSS colour, for a frontend that wants one
            // colour to match the panel.
            'primary_color_css' => $this->primaryColor()->palette()[500],
        ];
    }
}
