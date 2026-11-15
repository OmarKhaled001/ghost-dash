<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Defaults only. The installer overwrites the name and language with
        // what was entered, and the General settings page edits all of them.
        $this->migrator->add('general.app_name', config('app.name', 'GhostDash'));
        $this->migrator->add('general.logo_path');
        $this->migrator->add('general.favicon_path');
        $this->migrator->add('general.default_locale', config('app.locale', 'en'));
        $this->migrator->add('general.timezone', 'UTC');
        $this->migrator->add('general.contact_email');
    }
};
