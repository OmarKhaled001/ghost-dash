<?php

declare(strict_types=1);

return [

    'navigation' => [
        'access' => 'التحكم في الصلاحيات',
        'people' => 'الأشخاص',
        'system' => 'النظام',
    ],

    'auth' => [
        'deactivated' => [
            'title' => 'تم تعطيل حسابك',
            'body' => 'تم تسجيل خروجك. تواصل مع أحد المسؤولين إذا كنت تعتقد أن هذا خطأ.',
        ],
    ],

    'fields' => [
        'name' => 'الاسم',
        'email' => 'البريد الإلكتروني',
        'password' => 'كلمة المرور',
        'password_confirmation' => 'تأكيد كلمة المرور',
        'avatar' => 'الصورة الشخصية',
        'roles' => 'الأدوار',
        'is_active' => 'نشط',
        'email_verified' => 'البريد موثّق',
        'app_authentication' => 'تطبيق المصادقة',
        'email_authentication' => 'رموز البريد الإلكتروني',
        'last_login_at' => 'آخر تسجيل دخول',
        'last_login_ip' => 'عنوان IP لآخر تسجيل دخول',
        'created_at' => 'تاريخ الإنشاء',
        'guard_name' => 'نوع الحساب',
        'avatar_path' => 'الصورة الشخصية',
        'email_verified_at' => 'تاريخ توثيق البريد',
        'app_name' => 'اسم التطبيق',
        'logo_path' => 'الشعار',
        'favicon_path' => 'أيقونة الموقع',
        'default_locale' => 'اللغة الافتراضية',
        'timezone' => 'المنطقة الزمنية',
        'contact_email' => 'بريد التواصل',
    ],

    'admins' => [
        'label' => 'مسؤول',
        'plural' => 'المسؤولون',
        'never_signed_in' => 'لم يسجّل الدخول بعد',
        'sections' => [
            'account' => 'الحساب',
            'access' => 'الصلاحيات',
            'activity' => 'نشاط تسجيل الدخول',
        ],
        'hints' => [
            'password_unchanged' => 'اتركه فارغًا للإبقاء على كلمة المرور الحالية.',
            'roles' => 'المسؤول الذي لا يملك دورًا لا يستطيع تسجيل الدخول.',
            'is_active' => 'المسؤولون غير النشطين لا يستطيعون تسجيل الدخول ويُسجَّل خروجهم فورًا.',
        ],
        'actions' => [
            'activate' => 'تفعيل',
            'deactivate' => 'تعطيل',
            'deactivate_confirmation' => 'سيُسجَّل خروج هذا المسؤول ولن يتمكن من تسجيل الدخول مجددًا حتى يُعاد تفعيله.',
        ],
        'notifications' => [
            'activated' => 'تم تفعيل المسؤول',
            'deactivated' => 'تم تعطيل المسؤول',
        ],
    ],

    'users' => [
        'label' => 'مستخدم',
        'plural' => 'المستخدمون',
        'hints' => [
            'email_verified' => 'أوقفه ليُطلب من المستخدم تأكيد بريده مرة أخرى.',
        ],
    ],

    'guard_rails' => [
        'title' => 'هذا التغيير غير مسموح به',
        'cannot_delete_yourself' => 'لا يمكنك حذف حسابك.',
        'cannot_deactivate_yourself' => 'لا يمكنك تعطيل حسابك.',
        'last_super_admin' => 'هذا آخر مسؤول عام نشط. امنح شخصًا آخر دور المسؤول العام أولًا.',
        'super_admin_only' => 'المسؤول العام وحده يستطيع إنشاء المسؤولين العامين أو تعديلهم أو منح هذا الدور.',
        'super_admin_role_is_fixed' => 'دور المسؤول العام يملك كل الصلاحيات دائمًا ولا يمكن تعديله أو حذفه.',
        'role_in_use' => 'هذا الدور ما زال ممنوحًا لمسؤولين. انقلهم إلى دور آخر أولًا.',
        'cannot_grant_permissions_you_lack' => 'لا يمكنك منح صلاحيات لا تملكها.',
        'higher_authority' => 'هذا المسؤول يملك صلاحيات لا تملكها، لذلك لا يمكنك تعديل حسابه.',
        'unknown_role' => 'أحد الأدوار المحددة غير موجود.',
        'unknown_permission' => 'إحدى الصلاحيات المحددة غير موجودة.',
    ],

    // Labels for the custom permissions on the role form.
    'permissions' => [
        'view_telescope' => 'فتح Telescope',
        'view_activity_log' => 'عرض سجل النشاط',
        'manage_settings' => 'إدارة الإعدادات',
        'manage_backups' => 'إدارة النسخ الاحتياطية',
        'view_health' => 'عرض فحوصات السلامة',
    ],

    'activity' => [
        'label' => 'نشاط',
        'plural' => 'سجل النشاط',
        'timeline' => 'النشاط',
        'system' => 'النظام',
        'columns' => [
            'when' => 'الوقت',
            'log' => 'السجل',
            'event' => 'الحدث',
            'subject' => 'السجل المتأثر',
            'causer' => 'بواسطة',
            'property' => 'التفصيل',
            'value' => 'القيمة',
        ],
        'filters' => [
            'causer_type' => 'نوع المنفّذ',
            'subject_type' => 'نوع السجل',
            'from' => 'من',
            'until' => 'إلى',
        ],
        'sections' => [
            'changes' => 'التغييرات',
            'details' => 'التفاصيل',
        ],
        'diff' => [
            'attribute' => 'الحقل',
            'old' => 'قبل',
            'new' => 'بعد',
        ],
        'values' => [
            'yes' => 'نعم',
            'no' => 'لا',
        ],
        'logs' => [
            'admins' => 'المسؤولون',
            'users' => 'المستخدمون',
            'roles' => 'الأدوار',
            'authentication' => 'المصادقة',
            'settings' => 'الإعدادات',
            'backups' => 'النسخ الاحتياطية',
        ],
        'events' => [
            'created' => 'إنشاء',
            'updated' => 'تعديل',
            'deleted' => 'حذف',
            'roles_synced' => 'تغيير الأدوار',
            'permissions_synced' => 'تغيير الصلاحيات',
            'registered' => 'تسجيل حساب',
            'login' => 'تسجيل دخول',
            'logout' => 'تسجيل خروج',
            'failed_login' => 'محاولة دخول فاشلة',
            'lockout' => 'حظر مؤقت',
            'password_reset' => 'إعادة تعيين كلمة المرور',
            'email_verified' => 'توثيق البريد',
            'mfa_enabled' => 'تفعيل المصادقة المتعددة',
            'mfa_disabled' => 'تعطيل المصادقة المتعددة',
            'requested' => 'طلب',
            'downloaded' => 'تنزيل',
        ],
        'properties' => [
            'guard' => 'نوع الحساب',
            'ip' => 'عنوان IP',
            'user_agent' => 'المتصفح',
            'email' => 'البريد الإلكتروني',
            'provider' => 'الطريقة',
            'attached' => 'أُضيف',
            'detached' => 'أُزيل',
            'file' => 'الملف',
            'only_database' => 'قاعدة البيانات فقط',
            'group' => 'تبويب الإعدادات',
        ],
    ],

    'settings' => [
        'title' => 'الإعدادات',
        'save' => 'حفظ التغييرات',
        'saved' => 'تم الحفظ',
    ],

    'backups' => [
        'title' => 'النسخ الاحتياطية',
        'columns' => [
            'name' => 'الملف',
            'size' => 'الحجم',
            'created_at' => 'تاريخ الإنشاء',
        ],
        'empty' => [
            'heading' => 'لا توجد نسخ احتياطية بعد',
            'description' => 'تُنشأ النسخ الاحتياطية كل ليلة. تظهر هنا الملفات الموجودة على القرص ":disk".',
        ],
        'actions' => [
            'create' => 'إنشاء نسخة الآن',
            'create_database_only' => 'قاعدة البيانات فقط',
            'create_confirmation' => 'تُنشأ النسخة في الخلفية وتظهر في هذه القائمة عند اكتمالها. يجب أن يكون معالج الطوابير يعمل.',
            'download' => 'تنزيل',
            'delete' => 'حذف',
        ],
        'notifications' => [
            'requested' => [
                'title' => 'بدأ إنشاء النسخة الاحتياطية',
                'body' => 'ستظهر في القائمة عند اكتمالها.',
            ],
            'deleted' => 'تم حذف النسخة الاحتياطية',
        ],
    ],

    'health' => [
        'title' => 'سلامة النظام',
        'last_ran' => 'آخر فحص :time.',
        'never_ran' => 'لم تُشغَّل الفحوصات بعد. تعمل كل دقيقة بعد إعداد المُجدوِل، أو استخدم الزر أعلاه.',
        'actions' => [
            'refresh' => 'افحص الآن',
        ],
        'notifications' => [
            'refreshed' => 'اكتملت فحوصات السلامة',
        ],
        'statuses' => [
            'ok' => 'سليم',
            'warning' => 'تحذير',
            'failed' => 'فشل',
            'crashed' => 'تعطّل',
            'skipped' => 'تم تخطيه',
        ],
        'checks' => [
            'database' => 'الاتصال بقاعدة البيانات',
            'database_connection_count' => 'اتصالات قاعدة البيانات',
            'cache' => 'التخزين المؤقت',
            'queue' => 'معالج الطوابير',
            'schedule' => 'المُجدوِل',
            'disk_space' => 'مساحة القرص',
            'debug_mode' => 'وضع التصحيح',
            'environment' => 'البيئة',
            'optimized_app' => 'تهيئة التطبيق',
        ],
    ],

    'widgets' => [
        'stats' => [
            'users_today' => ':count سجّلوا اليوم',
            'admins_active' => ':count نشط',
        ],
    ],

];
