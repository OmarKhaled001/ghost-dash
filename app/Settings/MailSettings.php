<?php

declare(strict_types=1);

namespace App\Settings;

use App\Enums\MailEncryption;
use App\Enums\MailTransport;
use Spatie\LaravelSettings\Settings;

final class MailSettings extends Settings
{
    /**
     * Off: mail goes out as configured in .env. On: these values replace it.
     */
    public bool $enabled;

    public string $transport;

    public ?string $host = null;

    public ?int $port = null;

    public ?string $username = null;

    public ?string $password = null;

    public string $encryption;

    public ?string $from_address = null;

    public ?string $from_name = null;

    public static function group(): string
    {
        return 'mail';
    }

    /**
     * @return list<string>
     */
    public static function encrypted(): array
    {
        return ['password'];
    }

    /**
     * Laravel mail configuration built from these values, for config().
     *
     * @return array<string, mixed>
     */
    public function toConfig(): array
    {
        $transport = MailTransport::tryFrom($this->transport) ?? MailTransport::Smtp;
        $encryption = MailEncryption::tryFrom($this->encryption) ?? MailEncryption::StartTls;

        $config = [
            'mail.default' => $transport->value,
            'mail.mailers.smtp.scheme' => $encryption->scheme(),
            'mail.mailers.smtp.url' => null,
            'mail.mailers.smtp.host' => $this->host,
            'mail.mailers.smtp.port' => $this->port,
            'mail.mailers.smtp.username' => $this->username,
            'mail.mailers.smtp.password' => $this->password,
        ];

        if (filled($this->from_address)) {
            $config['mail.from.address'] = $this->from_address;
        }

        if (filled($this->from_name)) {
            $config['mail.from.name'] = $this->from_name;
        }

        return $config;
    }
}
