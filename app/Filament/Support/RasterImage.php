<?php

declare(strict_types=1);

namespace App\Filament\Support;

final class RasterImage
{
    /**
     * What an image upload may be. Filament's image() accepts "image/*",
     * which includes SVG, and an SVG can carry script that would run on this
     * application's own origin when the stored file is opened directly.
     *
     * @var list<string>
     */
    public const array MIME_TYPES = ['image/png', 'image/jpeg', 'image/webp'];
}
