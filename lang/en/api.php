<?php

declare(strict_types=1);

return [

    'messages' => [
        'ok' => 'OK.',
        'registered' => 'Your account has been created. Check your inbox to verify your email address.',
        'logged_in' => 'Signed in.',
        'logged_out' => 'Signed out.',
        'logged_out_everywhere' => 'Signed out on every device.',
        'password_reset_link_sent' => 'If that address belongs to an account, a password reset link has been sent to it.',
        'password_reset' => 'Your password has been reset. Sign in with the new password.',
        'password_updated' => 'Your password has been changed. Other devices have been signed out.',
        'email_verified' => 'Your email address has been verified.',
        'email_already_verified' => 'Your email address is already verified.',
        'verification_link_sent' => 'A new verification link has been sent to your email address.',
    ],

    'errors' => [
        'validation' => 'The given data was invalid.',
        'unauthenticated' => 'You are not signed in, or your session has expired.',
        'forbidden' => 'You are not allowed to do this.',
        'invalid_signature' => 'This link is invalid or has expired.',
        'not_found' => 'The requested resource was not found.',
        'method_not_allowed' => 'This method is not supported for the requested URL.',
        'session_expired' => 'The page has expired. Please try again.',
        'too_many_requests' => 'Too many attempts. Please wait before trying again.',
        'unavailable' => 'The service is temporarily unavailable. Please try again later.',
        'bad_request' => 'The request could not be processed.',
        'server' => 'Something went wrong on our side. Please try again later.',
    ],

];
