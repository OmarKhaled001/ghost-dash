<?php

declare(strict_types=1);

namespace App\Settings;

use App\Settings\Contracts\PublicSettings;
use Spatie\LaravelSettings\Settings;

final class ContactSettings extends Settings implements PublicSettings
{
    public ?string $email = null;

    public ?string $phone = null;

    /**
     * In international format, digits only, as wa.me links expect it.
     */
    public ?string $whatsapp = null;

    public ?string $address = null;

    public ?string $map_url = null;

    public static function group(): string
    {
        return 'contact';
    }

    public function toPublicArray(): array
    {
        return [
            'email' => $this->email,
            'phone' => $this->phone,
            'whatsapp' => $this->whatsapp,
            'whatsapp_url' => $this->whatsapp === null ? null : 'https://wa.me/'.$this->whatsapp,
            'address' => $this->address,
            'map_url' => $this->map_url,
        ];
    }
}
