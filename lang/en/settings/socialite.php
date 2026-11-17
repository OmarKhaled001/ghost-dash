<?php

declare(strict_types=1);

return [
    'label' => 'Social sign-in',
    'providers' => [
        'google' => 'Google',
        'facebook' => 'Facebook',
        'github' => 'GitHub',
        'x' => 'X (Twitter)',
        'linkedin' => 'LinkedIn',
    ],
    'fields' => [
        'enabled' => 'Let users sign in with this provider',
        'client_id' => 'Client ID',
        'client_secret' => 'Client secret',
    ],
    'hints' => [
        'endpoint' => 'Your app signs the user in with the provider, then sends the access token to :url.',
        'client_secret' => 'Stored encrypted and never shown. Leave empty to keep the current one.',
    ],
];
