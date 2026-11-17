<?php

declare(strict_types=1);

namespace App\Data;

/**
 * Everything a page head needs for SEO, already resolved: the page's own
 * values where it has them, the site-wide ones everywhere else.
 */
final readonly class SeoMeta
{
    public function __construct(
        public string $title,
        public string $siteName,
        public ?string $description,
        public ?string $keywords,
        public ?string $imageUrl,
        public ?string $canonicalUrl,
        public string $robots,
        public ?string $twitterHandle,
        public ?string $googleSiteVerification,
        public ?string $bingSiteVerification,
        public ?string $googleAnalyticsId,
        public ?string $googleTagManagerId,
    ) {}

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'site_name' => $this->siteName,
            'description' => $this->description,
            'keywords' => $this->keywords,
            'image_url' => $this->imageUrl,
            'canonical_url' => $this->canonicalUrl,
            'robots' => $this->robots,
            'twitter_handle' => $this->twitterHandle,
            'google_site_verification' => $this->googleSiteVerification,
            'bing_site_verification' => $this->bingSiteVerification,
            'google_analytics_id' => $this->googleAnalyticsId,
            'google_tag_manager_id' => $this->googleTagManagerId,
        ];
    }
}
