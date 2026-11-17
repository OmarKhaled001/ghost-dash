<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\TestMail;
use App\Settings\MailSettings;
use Illuminate\Support\Facades\Mail;
use SensitiveParameter;
use Throwable;

final readonly class MailService
{
    /**
     * Point the mailer at the Email settings, when they are switched on.
     * Called whenever the mail manager is first built, in web requests,
     * queue workers and commands alike.
     */
    public function applySettings(): void
    {
        try {
            $settings = resolve(MailSettings::class);
        } catch (Throwable) {
            // No database or no settings yet: an install in progress, or a
            // command that runs before migrations. .env stays in charge.
            return;
        }

        if ($settings->enabled) {
            config($settings->toConfig());
        }
    }

    /**
     * Send a test message with the given values, which may not be saved
     * yet. An empty password means the stored one. The mailer is rebuilt
     * before and after, so neither the test nor what follows it uses a
     * mailer built from other values.
     *
     * @param  array<string, mixed>  $values  MailSettings properties.
     *
     * @throws Throwable When the message cannot be sent; the message says why.
     */
    public function sendTest(#[SensitiveParameter] array $values, string $to): void
    {
        $settings = clone resolve(MailSettings::class);

        if (blank($values['password'] ?? null)) {
            unset($values['password']);
        }

        $settings->fill(array_intersect_key($values, $settings->toArray()));

        $original = config('mail');

        try {
            config($settings->toConfig());
            Mail::purge();

            Mail::to($to)->send(new TestMail);
        } finally {
            $tested = config('mail.default');

            config(['mail' => $original]);

            foreach (array_unique([$tested, config('mail.default')]) as $mailer) {
                Mail::purge(is_string($mailer) ? $mailer : null);
            }
        }
    }
}
