<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('seo.site_name');
        $this->migrator->add('seo.title_separator', '|');
        $this->migrator->add('seo.description');
        $this->migrator->add('seo.keywords');
        $this->migrator->add('seo.og_image_path');
        $this->migrator->add('seo.twitter_handle');
        $this->migrator->add('seo.index', true);
        $this->migrator->add('seo.follow', true);
        $this->migrator->add('seo.google_site_verification');
        $this->migrator->add('seo.bing_site_verification');
        $this->migrator->add('seo.google_analytics_id');
        $this->migrator->add('seo.google_tag_manager_id');
    }
};
