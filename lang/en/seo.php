<?php

declare(strict_types=1);

return [
    'navigation' => 'SEO',
    'site' => [
        'title' => 'Site SEO',
    ],
    'pages' => [
        'label' => 'page',
        'plural' => 'Pages',
    ],
    'sections' => [
        'search' => 'Search results',
        'page' => 'Page',
        'sharing' => 'Sharing',
        'indexing' => 'Indexing',
        'tools' => 'Verification and analytics',
    ],
    'fields' => [
        'path' => 'Path',
        'title' => 'Title',
        'description' => 'Description',
        'default_description' => 'Default description',
        'keywords' => 'Keywords',
        'og_image_path' => 'Sharing image',
        'canonical_url' => 'Canonical URL',
        'robots_index' => 'Index this page',
        'robots_follow' => 'Follow its links',
        'site_name' => 'Site name',
        'title_separator' => 'Title separator',
        'twitter_handle' => 'X (Twitter) account',
        'index' => 'Let search engines index the site',
        'follow' => 'Let search engines follow links',
        'google_site_verification' => 'Google Search Console code',
        'bing_site_verification' => 'Bing Webmaster code',
        'google_analytics_id' => 'Google Analytics ID',
        'google_tag_manager_id' => 'Google Tag Manager ID',
    ],
    'hints' => [
        'path' => 'The page\'s address on your website, such as / for the home page or /about. Query strings and a trailing slash are ignored.',
        'title' => 'Without the site name, which is added after it.',
        'description' => 'About 160 characters are shown in search results.',
        'keywords' => 'Separated by commas. Most search engines ignore them.',
        'og_image_path' => 'Shown when a link is shared. 1200 × 630 pixels works everywhere. PNG, JPEG or WebP, up to 2 MB.',
        'canonical_url' => 'Only when this content also lives at another address and that one should rank.',
        'site_name' => 'Shown after every page title. Empty uses the application name.',
        'index' => 'Turn off on a staging site, so it does not appear in search results.',
        'follow' => 'Lets search engines discover pages through the links on yours.',
    ],
    'robots' => [
        'inherit' => 'Like the site',
        'yes' => 'Yes',
        'no' => 'No',
    ],
    'columns' => [
        'indexed' => 'Indexed',
        'updated_at' => 'Updated',
    ],
    'validation' => [
        'path_taken' => 'This page already has SEO.',
    ],
];
