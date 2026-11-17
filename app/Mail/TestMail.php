<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sent from the Email tab of the Settings page to prove the settings work.
 */
final class TestMail extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: __('settings/mail.test.subject', ['app' => config('app.name')]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.test');
    }
}
