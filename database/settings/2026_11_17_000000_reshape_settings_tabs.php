<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * The Contact and Social media tabs gave way to Email and Socialite.
     * The contact address goes back to General with its value; the other
     * contact and social fields were never released and are dropped.
     */
    public function up(): void
    {
        if ($this->migrator->exists('contact.email')) {
            $this->migrator->rename('contact.email', 'general.contact_email');
        }

        foreach (['phone', 'whatsapp', 'address', 'map_url'] as $field) {
            $this->migrator->deleteIfExists("contact.{$field}");
        }

        foreach (['facebook', 'instagram', 'x', 'linkedin', 'youtube', 'tiktok', 'snapchat'] as $network) {
            $this->migrator->deleteIfExists("social.{$network}");
        }

        $this->migrator->add('mail.enabled', false);
        $this->migrator->add('mail.transport', 'smtp');
        $this->migrator->add('mail.host');
        $this->migrator->add('mail.port', 587);
        $this->migrator->add('mail.username');
        $this->migrator->addEncrypted('mail.password');
        $this->migrator->add('mail.encryption', 'starttls');
        $this->migrator->add('mail.from_address');
        $this->migrator->add('mail.from_name');

        foreach (['google', 'facebook', 'github', 'x', 'linkedin'] as $provider) {
            $this->migrator->add("socialite.{$provider}_enabled", false);
            $this->migrator->add("socialite.{$provider}_client_id");
            $this->migrator->addEncrypted("socialite.{$provider}_client_secret");
        }
    }
};
