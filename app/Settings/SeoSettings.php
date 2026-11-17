<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Site-wide SEO: the defaults every page starts from, and the tags that
 * belong to the site rather than to a page (verification, analytics).
 */
final class SeoSettings extends Settings
{
    /**
     * Shown after each page title. Empty means the application name.
     */
    public ?string $site_name = null;

    public string $title_separator;

    public ?string $description = null;

    public ?string $keywords = null;

    public ?string $og_image_path = null;

    public ?string $twitter_handle = null;

    public bool $index;

    public bool $follow;

    public ?string $google_site_verification = null;

    public ?string $bing_site_verification = null;

    public ?string $google_analytics_id = null;

    public ?string $google_tag_manager_id = null;

    public static function group(): string
    {
        return 'seo';
    }

    public function siteName(): string
    {
        return $this->site_name !== null && $this->site_name !== '' ? $this->site_name : resolve(GeneralSettings::class)->app_name;
    }
}
