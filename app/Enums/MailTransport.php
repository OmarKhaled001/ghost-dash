<?php

declare(strict_types=1);

namespace App\Enums;

enum MailTransport: string
{
    case Smtp = 'smtp';
    case Log = 'log';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])->all();
    }

    public function label(): string
    {
        return __('settings/mail.transports.'.$this->value);
    }
}
