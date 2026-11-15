<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Arabic counterparts of lang/en/validation.php. The two files must keep
    | the same keys; a test compares them.
    |
    */

    'accepted' => 'يجب قبول :attribute.',
    'accepted_if' => 'يجب قبول :attribute عندما يكون :other هو :value.',
    'active_url' => 'يجب أن يكون :attribute رابطًا صالحًا.',
    'after' => 'يجب أن يكون :attribute تاريخًا بعد :date.',
    'after_or_equal' => 'يجب أن يكون :attribute تاريخًا بعد :date أو مساويًا له.',
    'alpha' => 'يجب أن يحتوي :attribute على حروف فقط.',
    'alpha_dash' => 'يجب أن يحتوي :attribute على حروف وأرقام وشرطات وشرطات سفلية فقط.',
    'alpha_num' => 'يجب أن يحتوي :attribute على حروف وأرقام فقط.',
    'any_of' => ':attribute غير صالح.',
    'array' => 'يجب أن يكون :attribute مصفوفة.',
    'array_keys' => 'يجب أن يحتوي :attribute على المفاتيح التالية فقط: :values.',
    'ascii' => 'يجب أن يحتوي :attribute على حروف وأرقام ورموز لاتينية أحادية البايت فقط.',
    'base64' => 'يجب أن يكون :attribute نصًا صالحًا بترميز Base64.',
    'before' => 'يجب أن يكون :attribute تاريخًا قبل :date.',
    'before_or_equal' => 'يجب أن يكون :attribute تاريخًا قبل :date أو مساويًا له.',
    'between' => [
        'array' => 'يجب أن يحتوي :attribute على ما بين :min و:max عنصرًا.',
        'file' => 'يجب أن يكون حجم :attribute بين :min و:max كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة :attribute بين :min و:max.',
        'string' => 'يجب أن يكون طول :attribute بين :min و:max حرفًا.',
    ],
    'boolean' => 'يجب أن تكون قيمة :attribute صحيحة أو خاطئة.',
    'can' => 'يحتوي :attribute على قيمة غير مصرح بها.',
    'confirmed' => 'تأكيد :attribute غير متطابق.',
    'contains' => 'ينقص :attribute قيمة مطلوبة.',
    'current_password' => 'كلمة المرور غير صحيحة.',
    'date' => 'يجب أن يكون :attribute تاريخًا صالحًا.',
    'date_equals' => 'يجب أن يكون :attribute تاريخًا مساويًا لـ :date.',
    'date_format' => 'يجب أن يطابق :attribute الصيغة :format.',
    'decimal' => 'يجب أن يحتوي :attribute على :decimal منازل عشرية.',
    'declined' => 'يجب رفض :attribute.',
    'declined_if' => 'يجب رفض :attribute عندما يكون :other هو :value.',
    'different' => 'يجب أن يختلف :attribute عن :other.',
    'digits' => 'يجب أن يتكون :attribute من :digits رقمًا.',
    'digits_between' => 'يجب أن يتكون :attribute من :min إلى :max رقمًا.',
    'dimensions' => 'أبعاد صورة :attribute غير صالحة.',
    'distinct' => 'يحتوي :attribute على قيمة مكررة.',
    'doesnt_contain' => 'يجب ألا يحتوي :attribute على أي مما يلي: :values.',
    'doesnt_end_with' => 'يجب ألا ينتهي :attribute بأي مما يلي: :values.',
    'doesnt_start_with' => 'يجب ألا يبدأ :attribute بأي مما يلي: :values.',
    'email' => 'يجب أن يكون :attribute عنوان بريد إلكتروني صالحًا.',
    'encoding' => 'يجب أن يكون :attribute بترميز :encoding.',
    'ends_with' => 'يجب أن ينتهي :attribute بأحد القيم التالية: :values.',
    'enum' => ':attribute المحدد غير صالح.',
    'exists' => ':attribute المحدد غير صالح.',
    'extensions' => 'يجب أن يكون امتداد :attribute أحد الامتدادات التالية: :values.',
    'file' => 'يجب أن يكون :attribute ملفًا.',
    'filled' => 'يجب أن يحتوي :attribute على قيمة.',
    'gt' => [
        'array' => 'يجب أن يحتوي :attribute على أكثر من :value عنصرًا.',
        'file' => 'يجب أن يكون حجم :attribute أكبر من :value كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة :attribute أكبر من :value.',
        'string' => 'يجب أن يكون طول :attribute أكثر من :value حرفًا.',
    ],
    'gte' => [
        'array' => 'يجب أن يحتوي :attribute على :value عنصرًا أو أكثر.',
        'file' => 'يجب أن يكون حجم :attribute :value كيلوبايت أو أكثر.',
        'numeric' => 'يجب أن تكون قيمة :attribute :value أو أكثر.',
        'string' => 'يجب أن يكون طول :attribute :value حرفًا أو أكثر.',
    ],
    'hex_color' => 'يجب أن يكون :attribute لونًا صالحًا بالصيغة الست عشرية.',
    'image' => 'يجب أن يكون :attribute صورة.',
    'in' => ':attribute المحدد غير صالح.',
    'in_array' => 'يجب أن يكون :attribute موجودًا في :other.',
    'in_array_keys' => 'يجب أن يحتوي :attribute على مفتاح واحد على الأقل مما يلي: :values.',
    'integer' => 'يجب أن يكون :attribute عددًا صحيحًا.',
    'ip' => 'يجب أن يكون :attribute عنوان IP صالحًا.',
    'ipv4' => 'يجب أن يكون :attribute عنوان IPv4 صالحًا.',
    'ipv6' => 'يجب أن يكون :attribute عنوان IPv6 صالحًا.',
    'json' => 'يجب أن يكون :attribute نصًا صالحًا بصيغة JSON.',
    'list' => 'يجب أن يكون :attribute قائمة.',
    'lowercase' => 'يجب أن يكون :attribute بأحرف صغيرة.',
    'lt' => [
        'array' => 'يجب أن يحتوي :attribute على أقل من :value عنصرًا.',
        'file' => 'يجب أن يكون حجم :attribute أقل من :value كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة :attribute أقل من :value.',
        'string' => 'يجب أن يكون طول :attribute أقل من :value حرفًا.',
    ],
    'lte' => [
        'array' => 'يجب ألا يحتوي :attribute على أكثر من :value عنصرًا.',
        'file' => 'يجب أن يكون حجم :attribute :value كيلوبايت أو أقل.',
        'numeric' => 'يجب أن تكون قيمة :attribute :value أو أقل.',
        'string' => 'يجب أن يكون طول :attribute :value حرفًا أو أقل.',
    ],
    'mac_address' => 'يجب أن يكون :attribute عنوان MAC صالحًا.',
    'max' => [
        'array' => 'يجب ألا يحتوي :attribute على أكثر من :max عنصرًا.',
        'file' => 'يجب ألا يتجاوز حجم :attribute :max كيلوبايت.',
        'numeric' => 'يجب ألا تتجاوز قيمة :attribute :max.',
        'string' => 'يجب ألا يتجاوز طول :attribute :max حرفًا.',
    ],
    'max_digits' => 'يجب ألا يحتوي :attribute على أكثر من :max رقمًا.',
    'mimes' => 'يجب أن يكون :attribute ملفًا من نوع: :values.',
    'mimetypes' => 'يجب أن يكون :attribute ملفًا من نوع: :values.',
    'min' => [
        'array' => 'يجب أن يحتوي :attribute على :min عنصرًا على الأقل.',
        'file' => 'يجب ألا يقل حجم :attribute عن :min كيلوبايت.',
        'numeric' => 'يجب ألا تقل قيمة :attribute عن :min.',
        'string' => 'يجب ألا يقل طول :attribute عن :min حرفًا.',
    ],
    'min_digits' => 'يجب أن يحتوي :attribute على :min رقمًا على الأقل.',
    'missing' => 'يجب ألا يكون :attribute موجودًا.',
    'missing_if' => 'يجب ألا يكون :attribute موجودًا عندما يكون :other هو :value.',
    'missing_unless' => 'يجب ألا يكون :attribute موجودًا إلا إذا كان :other هو :value.',
    'missing_with' => 'يجب ألا يكون :attribute موجودًا عند وجود :values.',
    'missing_with_all' => 'يجب ألا يكون :attribute موجودًا عند وجود :values.',
    'multiple_of' => 'يجب أن تكون قيمة :attribute من مضاعفات :value.',
    'not_in' => ':attribute المحدد غير صالح.',
    'not_regex' => 'صيغة :attribute غير صالحة.',
    'numeric' => 'يجب أن يكون :attribute رقمًا.',
    'password' => [
        'letters' => 'يجب أن يحتوي :attribute على حرف واحد على الأقل.',
        'mixed' => 'يجب أن يحتوي :attribute على حرف كبير وحرف صغير على الأقل.',
        'numbers' => 'يجب أن يحتوي :attribute على رقم واحد على الأقل.',
        'symbols' => 'يجب أن يحتوي :attribute على رمز واحد على الأقل.',
        'uncompromised' => 'ظهر :attribute المدخل في تسريب بيانات. يرجى اختيار :attribute مختلف.',
    ],
    'present' => 'يجب أن يكون :attribute موجودًا.',
    'present_if' => 'يجب أن يكون :attribute موجودًا عندما يكون :other هو :value.',
    'present_unless' => 'يجب أن يكون :attribute موجودًا إلا إذا كان :other هو :value.',
    'present_with' => 'يجب أن يكون :attribute موجودًا عند وجود :values.',
    'present_with_all' => 'يجب أن يكون :attribute موجودًا عند وجود :values.',
    'prohibited' => ':attribute غير مسموح به.',
    'prohibited_if' => ':attribute غير مسموح به عندما يكون :other هو :value.',
    'prohibited_if_accepted' => ':attribute غير مسموح به عند قبول :other.',
    'prohibited_if_declined' => ':attribute غير مسموح به عند رفض :other.',
    'prohibited_unless' => ':attribute غير مسموح به إلا إذا كان :other ضمن :values.',
    'prohibits' => 'وجود :attribute يمنع وجود :other.',
    'regex' => 'صيغة :attribute غير صالحة.',
    'required' => ':attribute مطلوب.',
    'required_array_keys' => 'يجب أن يحتوي :attribute على مدخلات لكل من: :values.',
    'required_if' => ':attribute مطلوب عندما يكون :other هو :value.',
    'required_if_accepted' => ':attribute مطلوب عند قبول :other.',
    'required_if_declined' => ':attribute مطلوب عند رفض :other.',
    'required_unless' => ':attribute مطلوب إلا إذا كان :other ضمن :values.',
    'required_with' => ':attribute مطلوب عند وجود :values.',
    'required_with_all' => ':attribute مطلوب عند وجود :values.',
    'required_without' => ':attribute مطلوب عند عدم وجود :values.',
    'required_without_all' => ':attribute مطلوب عند عدم وجود أي من :values.',
    'same' => 'يجب أن يتطابق :attribute مع :other.',
    'size' => [
        'array' => 'يجب أن يحتوي :attribute على :size عنصرًا.',
        'file' => 'يجب أن يكون حجم :attribute :size كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة :attribute :size.',
        'string' => 'يجب أن يكون طول :attribute :size حرفًا.',
    ],
    'starts_with' => 'يجب أن يبدأ :attribute بأحد القيم التالية: :values.',
    'string' => 'يجب أن يكون :attribute نصًا.',
    'timezone' => 'يجب أن يكون :attribute منطقة زمنية صالحة.',
    'unique' => ':attribute مستخدم من قبل.',
    'uploaded' => 'فشل رفع :attribute.',
    'uppercase' => 'يجب أن يكون :attribute بأحرف كبيرة.',
    'url' => 'يجب أن يكون :attribute رابطًا صالحًا.',
    'ulid' => 'يجب أن يكون :attribute معرّف ULID صالحًا.',
    'uuid' => 'يجب أن يكون :attribute معرّف UUID صالحًا.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | Without these, messages would mix Arabic sentences with English field
    | names ("email مطلوب").
    |
    */

    'attributes' => [
        'name' => 'الاسم',
        'email' => 'البريد الإلكتروني',
        'password' => 'كلمة المرور',
        'current_password' => 'كلمة المرور الحالية',
        'token' => 'الرمز',
        'device_name' => 'اسم الجهاز',
        'abilities' => 'الصلاحيات',
    ],

];
