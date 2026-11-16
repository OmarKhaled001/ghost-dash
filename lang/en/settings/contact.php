<?php

declare(strict_types=1);

return [
    'label' => 'Contact',
    'fields' => [
        'email' => 'Email address',
        'phone' => 'Phone',
        'whatsapp' => 'WhatsApp number',
        'map_url' => 'Map link',
        'address' => 'Address',
    ],
    'hints' => [
        'whatsapp' => 'Digits only, with the country code and no leading zeros or plus sign, e.g. 201001234567.',
    ],
];
