<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How the SMTP connection is secured. STARTTLS upgrades a plain connection
 * (usually port 587) and is what Symfony Mailer does by default; SSL/TLS
 * encrypts from the first byte (usually port 465).
 */
enum MailEncryption: string
{
    case StartTls = 'starttls';
    case Ssl = 'ssl';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])->all();
    }

    /**
     * The value of Laravel's mail.mailers.smtp.scheme.
     */
    public function scheme(): string
    {
        return match ($this) {
            self::StartTls => 'smtp',
            self::Ssl => 'smtps',
        };
    }

    public function label(): string
    {
        return __('settings/mail.encryptions.'.$this->value);
    }
}
