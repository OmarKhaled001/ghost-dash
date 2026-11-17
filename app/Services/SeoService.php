<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\SeoMeta;
use App\Models\SeoPage;
use App\Repositories\Contracts\SeoPageRepository;
use App\Settings\GeneralSettings;
use App\Settings\SeoSettings;
use Illuminate\Support\Str;

final readonly class SeoService
{
    public function __construct(private SeoPageRepository $pages) {}

    /**
     * Switched on with `php artisan dash:seo` (GHOSTDASH_SEO=true).
     */
    public static function enabled(): bool
    {
        return (bool) config('ghostdash.seo.enabled');
    }

    /**
     * One spelling per page: "about", "/about/" and "/about?ref=x" are all
     * "/about". Full URLs are reduced to their path.
     */
    public static function normalizePath(string $path): string
    {
        $path = trim($path);

        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $path) === 1) {
            $path = (string) parse_url($path, PHP_URL_PATH);
        }

        $path = Str::before(Str::before($path, '?'), '#');
        $path = '/'.trim($path, '/');

        return (string) preg_replace('#/+#', '/', $path);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): SeoPage
    {
        return $this->pages->create($this->prepare($attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(SeoPage $page, array $attributes): SeoPage
    {
        return $this->pages->update($page, $this->prepare($attributes));
    }

    public function delete(SeoPage $page): void
    {
        $this->pages->delete($page);
    }

    /**
     * The SEO for a path, page values over site-wide ones. The overrides
     * come from the caller (a Blade view that knows its own title, say)
     * and win over both.
     *
     * @param  array{title?: string|null, description?: string|null, image?: string|null, canonical?: string|null}  $overrides
     */
    public function resolve(string $path, array $overrides = []): SeoMeta
    {
        $site = resolve(SeoSettings::class);
        $page = $this->pages->findByPath(self::normalizePath($path));

        $pageTitle = $overrides['title'] ?? $page?->title;
        $siteName = $site->siteName();

        return new SeoMeta(
            title: filled($pageTitle) ? "{$pageTitle} {$site->title_separator} {$siteName}" : $siteName,
            siteName: $siteName,
            description: $overrides['description'] ?? $page->description ?? $site->description,
            keywords: $page->keywords ?? $site->keywords,
            imageUrl: $overrides['image'] ?? GeneralSettings::publicUrl($page->og_image_path ?? $site->og_image_path),
            canonicalUrl: $overrides['canonical'] ?? $page?->canonical_url,
            robots: (($page->robots_index ?? $site->index) ? 'index' : 'noindex').', '.(($page->robots_follow ?? $site->follow) ? 'follow' : 'nofollow'),
            twitterHandle: $site->twitter_handle,
            googleSiteVerification: $site->google_site_verification,
            bingSiteVerification: $site->bing_site_verification,
            googleAnalyticsId: $site->google_analytics_id,
            googleTagManagerId: $site->google_tag_manager_id,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function prepare(array $attributes): array
    {
        if (isset($attributes['path']) && is_string($attributes['path'])) {
            $attributes['path'] = self::normalizePath($attributes['path']);
        }

        return $attributes;
    }
}
