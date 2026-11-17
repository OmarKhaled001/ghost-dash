<?php

declare(strict_types=1);

return [
    'navigation' => 'تحسين محركات البحث',
    'site' => [
        'title' => 'إعدادات الموقع',
    ],
    'pages' => [
        'label' => 'صفحة',
        'plural' => 'الصفحات',
    ],
    'sections' => [
        'search' => 'نتائج البحث',
        'page' => 'الصفحة',
        'sharing' => 'المشاركة',
        'indexing' => 'الأرشفة',
        'tools' => 'التحقق والتحليلات',
    ],
    'fields' => [
        'path' => 'المسار',
        'title' => 'العنوان',
        'description' => 'الوصف',
        'default_description' => 'الوصف الافتراضي',
        'keywords' => 'الكلمات المفتاحية',
        'og_image_path' => 'صورة المشاركة',
        'canonical_url' => 'الرابط الأساسي (Canonical)',
        'robots_index' => 'أرشفة هذه الصفحة',
        'robots_follow' => 'تتبع روابطها',
        'site_name' => 'اسم الموقع',
        'title_separator' => 'فاصل العنوان',
        'twitter_handle' => 'حساب إكس (تويتر)',
        'index' => 'السماح لمحركات البحث بأرشفة الموقع',
        'follow' => 'السماح لمحركات البحث بتتبع الروابط',
        'google_site_verification' => 'رمز Google Search Console',
        'bing_site_verification' => 'رمز Bing Webmaster',
        'google_analytics_id' => 'معرّف Google Analytics',
        'google_tag_manager_id' => 'معرّف Google Tag Manager',
    ],
    'hints' => [
        'path' => 'عنوان الصفحة في موقعك، مثل / للصفحة الرئيسية أو ‎/about. تُتجاهل معاملات الاستعلام والشرطة المائلة في النهاية.',
        'title' => 'بدون اسم الموقع، فهو يُضاف بعده.',
        'description' => 'تعرض نتائج البحث نحو 160 حرفًا.',
        'keywords' => 'مفصولة بفواصل. معظم محركات البحث تتجاهلها.',
        'og_image_path' => 'تظهر عند مشاركة رابط. المقاس 1200 × 630 بكسل مناسب لكل المنصات. PNG أو JPEG أو WebP بحجم لا يتجاوز 2 ميغابايت.',
        'canonical_url' => 'فقط إذا كان المحتوى نفسه موجودًا على عنوان آخر وتريد أن يظهر ذلك العنوان في البحث.',
        'site_name' => 'يظهر بعد عنوان كل صفحة. اتركه فارغًا لاستخدام اسم التطبيق.',
        'index' => 'أوقفه في موقع التجربة حتى لا يظهر في نتائج البحث.',
        'follow' => 'يسمح لمحركات البحث باكتشاف الصفحات عبر الروابط الموجودة في صفحاتك.',
    ],
    'robots' => [
        'inherit' => 'مثل إعداد الموقع',
        'yes' => 'نعم',
        'no' => 'لا',
    ],
    'columns' => [
        'indexed' => 'مؤرشفة',
        'updated_at' => 'آخر تحديث',
    ],
    'validation' => [
        'path_taken' => 'لهذه الصفحة إعدادات تحسين محركات بحث بالفعل.',
    ],
];
