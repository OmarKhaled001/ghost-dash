<?php

declare(strict_types=1);

use App\Filament\Pages\ManageSiteSeo;
use App\Filament\Resources\SeoPages\Pages\CreateSeoPage;
use App\Filament\Resources\SeoPages\Pages\EditSeoPage;
use App\Filament\Resources\SeoPages\Pages\ListSeoPages;
use App\Models\Activity;
use App\Models\SeoPage;
use App\Services\SeoService;
use App\Settings\GeneralSettings;
use App\Settings\SeoSettings;
use Filament\Actions\DeleteAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Livewire\livewire;

beforeEach(function (): void {
    actingAsAdmin(adminWith(['manage_seo', 'view_any:seo_page', 'view:seo_page', 'create:seo_page', 'update:seo_page', 'delete:seo_page']));

    $general = resolve(GeneralSettings::class);
    $general->app_name = 'Acme';
    $general->save();
});

function siteSeo(array $values): void
{
    $seo = resolve(SeoSettings::class);
    $seo->fill($values);
    $seo->save();
}

describe('paths', function (): void {
    it('spells every page one way', function (string $input, string $expected): void {
        expect(SeoService::normalizePath($input))->toBe($expected);
    })->with([
        ['/', '/'],
        ['', '/'],
        ['about', '/about'],
        ['/about/', '/about'],
        ['/about?ref=mail#team', '/about'],
        ['//blog//post/', '/blog/post'],
        ['https://acme.test/pricing/?x=1', '/pricing'],
    ]);
});

describe('pages resource', function (): void {
    it('lists the pages', function (): void {
        $pages = SeoPage::factory()->count(3)->create();

        livewire(ListSeoPages::class)->assertCanSeeTableRecords($pages);
    });

    it('creates a page with its path normalised, through the service, and logs it', function (): void {
        livewire(CreateSeoPage::class)
            ->fillForm([
                'path' => 'about/?utm=x',
                'title' => 'About us',
                'description' => 'Who we are.',
                'robots_index' => 0,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $page = SeoPage::query()->sole();

        expect($page->path)->toBe('/about')
            ->and($page->title)->toBe('About us')
            ->and($page->robots_index)->toBeFalse()
            ->and($page->robots_follow)->toBeNull()
            ->and(Activity::query()->where('log_name', 'seo')->where('event', 'created')->exists())->toBeTrue();
    });

    it('refuses a second entry for the same page, however it is spelled', function (): void {
        SeoPage::factory()->create(['path' => '/about']);

        livewire(CreateSeoPage::class)
            ->fillForm(['path' => '/about/'])
            ->call('create')
            ->assertHasFormErrors(['path']);

        expect(SeoPage::query()->count())->toBe(1);
    });

    it('edits a page and keeps its own path valid', function (): void {
        $page = SeoPage::factory()->create(['path' => '/about', 'title' => 'Old']);

        livewire(EditSeoPage::class, ['record' => $page->getKey()])
            ->fillForm(['path' => '/about', 'title' => 'New', 'robots_index' => null])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($page->refresh()->title)->toBe('New')
            ->and($page->robots_index)->toBeNull();
    });

    it('deletes a page', function (): void {
        $page = SeoPage::factory()->create();

        livewire(ListSeoPages::class)->callTableAction(DeleteAction::class, $page);

        expect(SeoPage::query()->count())->toBe(0);
    });

    it('validates the canonical link and the path', function (array $input, string $field): void {
        livewire(CreateSeoPage::class)
            ->fillForm(['path' => '/ok', ...$input])
            ->call('create')
            ->assertHasFormErrors([$field]);
    })->with([
        'path is required' => [['path' => ''], 'path'],
        'path has no spaces' => [['path' => '/about us'], 'path'],
        'canonical must be a link' => [['canonical_url' => 'javascript:alert(1)'], 'canonical_url'],
    ]);
});

describe('site SEO page', function (): void {
    it('saves the site-wide SEO through the settings service', function (): void {
        livewire(ManageSiteSeo::class)
            ->fillForm([
                'site_name' => 'Acme Inc',
                'title_separator' => '-',
                'description' => 'Tools for teams.',
                'google_analytics_id' => 'G-ABC123XYZ',
                'index' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $seo = resolve(SeoSettings::class)->refresh();

        expect($seo->siteName())->toBe('Acme Inc')
            ->and($seo->title_separator)->toBe('-')
            ->and($seo->index)->toBeFalse()
            ->and(Activity::query()->where('log_name', 'settings')->latest('id')->firstOrFail()->properties->get('group'))->toBe('seo');
    });

    it('accepts only well-formed analytics and verification codes', function (string $field, string $value): void {
        livewire(ManageSiteSeo::class)
            ->fillForm([$field => $value])
            ->call('save')
            ->assertHasFormErrors([$field]);
    })->with([
        ['google_analytics_id', "G-1');alert('x"],
        ['google_tag_manager_id', 'GTM-<script>'],
        ['twitter_handle', 'not a handle'],
        ['google_site_verification', 'short'],
    ]);
});

describe('resolving a page', function (): void {
    it('falls back to the site-wide SEO for a page without its own', function (): void {
        siteSeo(['description' => 'Tools for teams.', 'og_image_path' => 'seo/site.png', 'twitter_handle' => '@acme']);

        $meta = resolve(SeoService::class)->resolve('/unknown');

        expect($meta->title)->toBe('Acme')
            ->and($meta->description)->toBe('Tools for teams.')
            ->and($meta->imageUrl)->toEndWith('/storage/seo/site.png')
            ->and($meta->robots)->toBe('index, follow')
            ->and($meta->twitterHandle)->toBe('@acme');
    });

    it('uses the page\'s own values over the site\'s, and the caller\'s over both', function (): void {
        siteSeo(['description' => 'Site', 'index' => true, 'follow' => true]);
        SeoPage::factory()->create(['path' => '/pricing', 'title' => 'Pricing', 'description' => 'Plans', 'robots_index' => false, 'robots_follow' => null]);

        $meta = resolve(SeoService::class)->resolve('/pricing/?ref=1');

        expect($meta->title)->toBe('Pricing | Acme')
            ->and($meta->description)->toBe('Plans')
            ->and($meta->robots)->toBe('noindex, follow');

        expect(resolve(SeoService::class)->resolve('/pricing', ['title' => 'Plans for teams'])->title)->toBe('Plans for teams | Acme');
    });
});

describe('<x-seo>', function (): void {
    it('writes the head tags for the current page, escaped', function (): void {
        siteSeo(['google_analytics_id' => 'G-ABC123XYZ', 'google_site_verification' => 'abcdefghijkl']);
        SeoPage::factory()->create(['path' => '/about', 'title' => 'About', 'description' => 'Fast "quiet" <secure>']);

        app()->instance('request', Request::create('https://acme.test/about'));

        $html = Blade::render('<x-seo />');

        expect($html)->toContain('<title>About | Acme</title>')
            ->toContain('<meta name="robots" content="index, follow">')
            ->toContain('<meta property="og:title" content="About | Acme">')
            ->toContain('content="Fast &quot;quiet&quot; &lt;secure&gt;"')
            ->toContain('<meta name="google-site-verification" content="abcdefghijkl">')
            ->toContain('gtag/js?id=G-ABC123XYZ')
            ->not->toContain('<secure>')
            ->not->toContain('googletagmanager.com/gtm.js');
    });

    it('takes a title from the view', function (): void {
        expect(Blade::render('<x-seo title="Checkout" />'))->toContain('<title>Checkout | Acme</title>');
    });

    it('renders nothing while SEO is off', function (): void {
        config(['ghostdash.seo.enabled' => false]);

        expect(trim(Blade::render('<x-seo title="Checkout" />')))->toBe('');
    });
});

describe('API', function (): void {
    it('returns the SEO for a path, without signing in', function (): void {
        SeoPage::factory()->create(['path' => '/about', 'title' => 'About']);

        $response = getJson('/api/v1/seo?path=/about/')->assertOk();

        assertEnvelope($response, success: true);

        expect($response->json('data.title'))->toBe('About | Acme')
            ->and($response->json('data.robots'))->toBe('index, follow')
            ->and(getJson('/api/v1/seo')->json('data.title'))->toBe('Acme');
    });
});

describe('switched off', function (): void {
    beforeEach(function (): void {
        config(['ghostdash.seo.enabled' => false]);
    });

    it('hides the SEO menu and refuses its pages, even to a super admin', function (): void {
        actingAsAdmin(superAdmin());

        get(panelUrl())->assertOk()->assertDontSee(__('seo.navigation'));
        get(panelUrl('seo/pages'))->assertForbidden();
        get(panelUrl('seo/site'))->assertForbidden();
    });

    it('answers 404 on the API', function (): void {
        getJson('/api/v1/seo')->assertNotFound();
    });
});

describe('dash:seo', function (): void {
    beforeEach(function (): void {
        $this->scratch = storage_path('framework/testing/seo-'.uniqid());
        File::ensureDirectoryExists($this->scratch);
        File::put($this->scratch.'/.env', "APP_NAME=Acme\nGHOSTDASH_SEO=false\n");
        $this->app->useEnvironmentPath($this->scratch);
    });

    afterEach(function (): void {
        File::deleteDirectory($this->scratch);
    });

    it('switches SEO on in .env, and off again', function (): void {
        config(['ghostdash.seo.enabled' => false]);

        $this->artisan('dash:seo')->assertSuccessful();

        expect(File::get($this->scratch.'/.env'))->toContain('GHOSTDASH_SEO=true')
            ->and(config('ghostdash.seo.enabled'))->toBeTrue();

        $this->artisan('dash:seo', ['--disable' => true])->assertSuccessful();

        expect(File::get($this->scratch.'/.env'))->toContain('GHOSTDASH_SEO=false');
    });

    it('leaves a server\'s environment to its deployment', function (): void {
        app()->detectEnvironment(fn (): string => 'production');

        $this->artisan('dash:seo')->assertFailed();

        expect(File::get($this->scratch.'/.env'))->toContain('GHOSTDASH_SEO=false');
    });
});
