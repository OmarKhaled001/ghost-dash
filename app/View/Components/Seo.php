<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Data\SeoMeta;
use App\Services\SeoService;
use Illuminate\View\Component;

/**
 * SEO head tags for a public page, from the SEO menu of the panel: the SEO
 * stored for this page's path, the site-wide SEO for everything else.
 *
 *     <head>
 *         <x-seo />
 *         <x-seo title="Pricing" description="Plans for every team." />
 *     </head>
 *
 * Attributes win over what is stored. With SEO switched off it renders
 * nothing, so a layout can include it either way.
 */
final class Seo extends Component
{
    public ?SeoMeta $meta = null;

    public string $url;

    public function __construct(
        SeoService $seo,
        ?string $title = null,
        ?string $description = null,
        ?string $image = null,
        ?string $canonical = null,
        public string $type = 'website',
    ) {
        $this->url = url()->current();

        if (SeoService::enabled()) {
            $this->meta = $seo->resolve(request()->path(), array_filter([
                'title' => $title,
                'description' => $description,
                'image' => $image,
                'canonical' => $canonical,
            ], filled(...)));
        }
    }

    public function shouldRender(): bool
    {
        return $this->meta instanceof SeoMeta;
    }

    public function render(): string
    {
        return <<<'blade'
            <title>{{ $meta->title }}</title>
            @if (filled($meta->description))
                <meta name="description" content="{{ $meta->description }}">
            @endif
            @if (filled($meta->keywords))
                <meta name="keywords" content="{{ $meta->keywords }}">
            @endif
            <meta name="robots" content="{{ $meta->robots }}">
            <link rel="canonical" href="{{ $meta->canonicalUrl ?? $url }}">

            <meta property="og:type" content="{{ $type }}">
            <meta property="og:site_name" content="{{ $meta->siteName }}">
            <meta property="og:title" content="{{ $meta->title }}">
            <meta property="og:url" content="{{ $meta->canonicalUrl ?? $url }}">
            <meta property="og:locale" content="{{ str_replace('-', '_', app()->getLocale()) }}">
            @if (filled($meta->description))
                <meta property="og:description" content="{{ $meta->description }}">
            @endif
            @if (filled($meta->imageUrl))
                <meta property="og:image" content="{{ $meta->imageUrl }}">
            @endif

            <meta name="twitter:card" content="{{ filled($meta->imageUrl) ? 'summary_large_image' : 'summary' }}">
            <meta name="twitter:title" content="{{ $meta->title }}">
            @if (filled($meta->twitterHandle))
                <meta name="twitter:site" content="{{ $meta->twitterHandle }}">
            @endif
            @if (filled($meta->description))
                <meta name="twitter:description" content="{{ $meta->description }}">
            @endif
            @if (filled($meta->imageUrl))
                <meta name="twitter:image" content="{{ $meta->imageUrl }}">
            @endif

            @if (filled($meta->googleSiteVerification))
                <meta name="google-site-verification" content="{{ $meta->googleSiteVerification }}">
            @endif
            @if (filled($meta->bingSiteVerification))
                <meta name="msvalidate.01" content="{{ $meta->bingSiteVerification }}">
            @endif

            @if (filled($meta->googleTagManagerId))
                <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer',@js($meta->googleTagManagerId));</script>
            @endif
            @if (filled($meta->googleAnalyticsId))
                <script async src="https://www.googletagmanager.com/gtag/js?id={{ urlencode($meta->googleAnalyticsId) }}"></script>
                <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config',@js($meta->googleAnalyticsId));</script>
            @endif
            blade;
    }
}
