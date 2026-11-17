<?php

declare(strict_types=1);

return [
    'label' => 'General',
    'sections' => [
        'identity' => 'Identity',
        'regional' => 'Language and region',
    ],
    'fields' => [
        'app_name' => 'Application name',
        'logo_path' => 'Logo',
        'favicon_path' => 'Favicon',
        'default_locale' => 'Default language',
        'contact_email' => 'Contact email',
        'timezone' => 'Time zone',
    ],
    'hints' => [
        'logo_path' => 'PNG, JPEG or WebP, up to 1 MB. Shown in place of the name.',
        'favicon_path' => 'PNG, JPEG or WebP, up to 512 KB.',
        'default_locale' => 'Used until someone picks a language themselves.',
        'timezone' => 'Dates in the panel are shown in this time zone.',
    ],
];
