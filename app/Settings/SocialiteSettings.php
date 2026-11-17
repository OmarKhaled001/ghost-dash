<?php

declare(strict_types=1);

namespace App\Settings;

use App\Enums\SocialProvider;
use App\Settings\Contracts\PublicSettings;
use Spatie\LaravelSettings\Settings;

final class SocialiteSettings extends Settings implements PublicSettings
{
    public bool $google_enabled;

    public ?string $google_client_id = null;

    public ?string $google_client_secret = null;

    public bool $facebook_enabled;

    public ?string $facebook_client_id = null;

    public ?string $facebook_client_secret = null;

    public bool $github_enabled;

    public ?string $github_client_id = null;

    public ?string $github_client_secret = null;

    public bool $x_enabled;

    public ?string $x_client_id = null;

    public ?string $x_client_secret = null;

    public bool $linkedin_enabled;

    public ?string $linkedin_client_id = null;

    public ?string $linkedin_client_secret = null;

    public static function group(): string
    {
        return 'socialite';
    }

    /**
     * @return list<string>
     */
    public static function encrypted(): array
    {
        return array_map(fn (SocialProvider $provider): string => "{$provider->value}_client_secret", SocialProvider::cases());
    }

    /**
     * Switched on and complete: a provider without credentials cannot work.
     */
    public function isEnabled(SocialProvider $provider): bool
    {
        return $this->{"{$provider->value}_enabled"} === true
            && filled($this->clientId($provider))
            && filled($this->clientSecret($provider));
    }

    public function clientId(SocialProvider $provider): ?string
    {
        return $this->{"{$provider->value}_client_id"};
    }

    public function clientSecret(SocialProvider $provider): ?string
    {
        return $this->{"{$provider->value}_client_secret"};
    }

    /**
     * @return list<SocialProvider>
     */
    public function enabledProviders(): array
    {
        return array_values(array_filter(SocialProvider::cases(), $this->isEnabled(...)));
    }

    /**
     * Which sign-in buttons a frontend should show. Never the credentials.
     */
    public function toPublicArray(): array
    {
        return [
            'providers' => array_map(fn (SocialProvider $provider): string => $provider->value, $this->enabledProviders()),
        ];
    }
}
