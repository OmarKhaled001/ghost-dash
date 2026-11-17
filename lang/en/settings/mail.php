<?php

declare(strict_types=1);

return [
    'label' => 'Email',
    'sections' => [
        'test' => 'Test',
    ],
    'fields' => [
        'enabled' => 'Use these settings to send email',
        'transport' => 'Send with',
        'encryption' => 'Encryption',
        'host' => 'SMTP server',
        'port' => 'Port',
        'username' => 'Username',
        'password' => 'Password',
        'from_address' => 'From address',
        'from_name' => 'From name',
    ],
    'hints' => [
        'enabled' => 'Off: email is sent as configured in the server\'s .env file.',
        'password' => 'Stored encrypted and never shown. Leave empty to keep the current one.',
        'test' => 'Sends a message with the values above, saved or not.',
    ],
    'transports' => [
        'smtp' => 'SMTP server',
        'log' => 'Write to the log file (for testing)',
    ],
    'encryptions' => [
        'starttls' => 'STARTTLS (usually port 587)',
        'ssl' => 'SSL/TLS (usually port 465)',
    ],
    'test' => [
        'action' => 'Send a test email',
        'to' => 'Send to',
        'sent' => 'Test email sent to :email',
        'failed' => 'The test email could not be sent',
        'subject' => 'Test email from :app',
        'heading' => 'It works',
        'body' => 'This message was sent from the Email settings of :app, so the settings in use there can deliver mail.',
        'sent_at' => 'Sent :time.',
    ],
];
