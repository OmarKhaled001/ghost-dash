<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // The contact address moves to its own tab, with what was entered.
        $this->migrator->rename('general.contact_email', 'contact.email');
        $this->migrator->add('contact.phone');
        $this->migrator->add('contact.whatsapp');
        $this->migrator->add('contact.address');
        $this->migrator->add('contact.map_url');

        foreach (['facebook', 'instagram', 'x', 'linkedin', 'youtube', 'tiktok', 'snapchat'] as $network) {
            $this->migrator->add("social.{$network}");
        }

        $this->migrator->add('appearance.primary_color', 'indigo');
    }
};
