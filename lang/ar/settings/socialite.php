<?php

declare(strict_types=1);

return [
    'label' => 'تسجيل الدخول الاجتماعي',
    'providers' => [
        'google' => 'جوجل',
        'facebook' => 'فيسبوك',
        'github' => 'GitHub',
        'x' => 'إكس (تويتر)',
        'linkedin' => 'لينكدإن',
    ],
    'fields' => [
        'enabled' => 'السماح للمستخدمين بتسجيل الدخول بهذا المزوّد',
        'client_id' => 'معرّف العميل (Client ID)',
        'client_secret' => 'سر العميل (Client secret)',
    ],
    'hints' => [
        'endpoint' => 'يسجّل تطبيقك دخول المستخدم لدى المزوّد، ثم يرسل رمز الوصول إلى :url.',
        'client_secret' => 'يُحفظ مشفّرًا ولا يُعرض أبدًا. اتركه فارغًا للإبقاء على الحالي.',
    ],
];
