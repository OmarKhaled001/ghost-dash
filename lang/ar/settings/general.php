<?php

declare(strict_types=1);

return [
    'label' => 'عام',
    'sections' => [
        'identity' => 'الهوية',
        'regional' => 'اللغة والمنطقة',
    ],
    'fields' => [
        'app_name' => 'اسم التطبيق',
        'logo_path' => 'الشعار',
        'favicon_path' => 'أيقونة الموقع',
        'default_locale' => 'اللغة الافتراضية',
        'contact_email' => 'بريد التواصل',
        'timezone' => 'المنطقة الزمنية',
    ],
    'hints' => [
        'logo_path' => 'PNG أو JPEG أو WebP بحجم لا يتجاوز 1 ميغابايت. يظهر بدلًا من الاسم.',
        'favicon_path' => 'PNG أو JPEG أو WebP بحجم لا يتجاوز 512 كيلوبايت.',
        'default_locale' => 'تُستخدم إلى أن يختار المستخدم لغته بنفسه.',
        'timezone' => 'تُعرض التواريخ في لوحة التحكم بهذه المنطقة الزمنية.',
    ],
];
