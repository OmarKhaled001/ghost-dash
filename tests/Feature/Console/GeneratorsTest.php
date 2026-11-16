<?php

declare(strict_types=1);

use App\Filament\Settings\Tabs\SeoTab;
use App\Models\User;
use App\Services\PublicSettingsService;
use App\Settings\GeneralSettings;
use App\Settings\SeoSettings;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;

/*
| The generators write into app_path(). These tests point that at a scratch
| directory holding a copy of RepositoryServiceProvider, so nothing in the
| real application is touched while other test processes are loading it.
*/

beforeEach(function (): void {
    $this->scratch = storage_path('framework/testing/generators-'.uniqid());

    File::ensureDirectoryExists($this->scratch.'/Providers');
    File::copy(app_path('Providers/RepositoryServiceProvider.php'), $this->scratch.'/Providers/RepositoryServiceProvider.php');

    $this->app->useAppPath($this->scratch);
});

afterEach(function (): void {
    File::deleteDirectory($this->scratch);
});

function lint(string $path): bool
{
    exec(PHP_BINARY.' -l '.escapeshellarg($path).' 2>&1', $output, $exitCode);

    return $exitCode === 0;
}

describe('make:service', function (): void {
    it('creates a final, readonly, strictly typed service', function (): void {
        $this->artisan('make:service', ['name' => 'Billing/InvoiceService'])->assertSuccessful();

        $path = $this->scratch.'/Services/Billing/InvoiceService.php';
        $source = File::get($path);

        expect($source)->toContain('declare(strict_types=1);')
            ->toContain('namespace App\Services\Billing;')
            ->toContain('final readonly class InvoiceService')
            ->and(lint($path))->toBeTrue();
    });

    it('adds the Service suffix when it is left out', function (): void {
        $this->artisan('make:service', ['name' => 'Invoice'])->assertSuccessful();

        expect(File::exists($this->scratch.'/Services/InvoiceService.php'))->toBeTrue()
            ->and(File::exists($this->scratch.'/Services/Invoice.php'))->toBeFalse();
    });

    it('does not overwrite an existing service', function (): void {
        $this->artisan('make:service', ['name' => 'Invoice'])->assertSuccessful();
        File::put($this->scratch.'/Services/InvoiceService.php', '<?php // edited');

        $this->artisan('make:service', ['name' => 'Invoice']);

        expect(File::get($this->scratch.'/Services/InvoiceService.php'))->toBe('<?php // edited');
    });
});

describe('make:repository', function (): void {
    it('creates the contract, the Eloquent implementation and the binding', function (): void {
        $this->artisan('make:repository', ['name' => 'Invoice'])->assertSuccessful();

        $contract = $this->scratch.'/Repositories/Contracts/InvoiceRepository.php';
        $implementation = $this->scratch.'/Repositories/Eloquent/EloquentInvoiceRepository.php';
        $provider = File::get($this->scratch.'/Providers/RepositoryServiceProvider.php');

        expect(File::get($contract))
            ->toContain('declare(strict_types=1);')
            ->toContain('namespace App\Repositories\Contracts;')
            ->toContain('interface InvoiceRepository')
            ->toContain('use App\Models\Invoice;')
            ->toContain('public function update(Invoice $invoice, array $attributes): Invoice;')
            ->and(File::get($implementation))
            ->toContain('namespace App\Repositories\Eloquent;')
            ->toContain('final class EloquentInvoiceRepository implements InvoiceRepository')
            ->toContain('use App\Repositories\Contracts\InvoiceRepository;')
            ->and($provider)
            ->toContain('use App\Repositories\Contracts\InvoiceRepository;')
            ->toContain('use App\Repositories\Eloquent\EloquentInvoiceRepository;')
            ->toContain('InvoiceRepository::class => EloquentInvoiceRepository::class,')
            // What was there before is still there.
            ->toContain('AdminRepository::class => EloquentAdminRepository::class,')
            ->toContain('UserRepository::class => EloquentUserRepository::class,')
            ->and(lint($contract))->toBeTrue()
            ->and(lint($implementation))->toBeTrue()
            ->and(lint($this->scratch.'/Providers/RepositoryServiceProvider.php'))->toBeTrue();
    });

    it('keeps the provider imports in the order Pint expects', function (): void {
        $this->artisan('make:repository', ['name' => 'Invoice'])->assertSuccessful();

        preg_match_all('/^use ([^;]+);$/m', File::get($this->scratch.'/Providers/RepositoryServiceProvider.php'), $matches);

        $sorted = $matches[1];
        usort($sorted, strcasecmp(...));

        expect($matches[1])->toBe($sorted);
    });

    it('accepts the name with or without the Repository suffix', function (): void {
        $this->artisan('make:repository', ['name' => 'InvoiceRepository'])->assertSuccessful();

        expect(File::exists($this->scratch.'/Repositories/Contracts/InvoiceRepository.php'))->toBeTrue()
            ->and(File::exists($this->scratch.'/Repositories/Contracts/InvoiceRepositoryRepository.php'))->toBeFalse();
    });

    it('uses the model given with --model', function (): void {
        $this->artisan('make:repository', ['name' => 'Customer', '--model' => User::class])->assertSuccessful();

        expect(File::get($this->scratch.'/Repositories/Eloquent/EloquentCustomerRepository.php'))
            ->toContain('use App\Models\User;')
            ->toContain('public function find(int $id): ?User')
            ->toContain('final class EloquentCustomerRepository implements CustomerRepository');
    });

    it('binds only once when run again with --force', function (): void {
        $this->artisan('make:repository', ['name' => 'Invoice'])->assertSuccessful();
        $this->artisan('make:repository', ['name' => 'Invoice', '--force' => true])->assertSuccessful();

        $provider = File::get($this->scratch.'/Providers/RepositoryServiceProvider.php');

        expect(substr_count($provider, 'InvoiceRepository::class => EloquentInvoiceRepository::class,'))->toBe(1)
            ->and(substr_count($provider, 'use App\Repositories\Contracts\InvoiceRepository;'))->toBe(1);
    });

    it('refuses to overwrite existing files without --force', function (): void {
        $this->artisan('make:repository', ['name' => 'Invoice'])->assertSuccessful();
        File::put($this->scratch.'/Repositories/Contracts/InvoiceRepository.php', '<?php // edited');

        $this->artisan('make:repository', ['name' => 'Invoice'])->assertFailed();

        expect(File::get($this->scratch.'/Repositories/Contracts/InvoiceRepository.php'))->toBe('<?php // edited');
    });
});

it('ships stubs that declare strict types and final classes', function (): void {
    foreach (['service', 'repository.contract', 'repository.eloquent', 'model', 'controller.plain', 'request', 'resource', 'job.queued', 'policy.plain', 'middleware'] as $stub) {
        expect(File::get(base_path("stubs/{$stub}.stub")))->toContain('declare(strict_types=1);');
    }

    expect(File::get(base_path('stubs/model.stub')))->toContain('final class {{ class }}')
        ->and(File::get(base_path('stubs/repository.eloquent.stub')))->toContain('final class {{ class }}')
        ->and(File::get(base_path('stubs/service.stub')))->toContain('final readonly class {{ class }}');
});

describe('make:settings-tab', function (): void {
    beforeEach(function (): void {
        $this->app->useDatabasePath($this->scratch.'/database');
        $this->app->useLangPath($this->scratch.'/lang');
    });

    it('creates the settings class, its migration, the tab and both translations', function (): void {
        $this->artisan('make:settings-tab', ['name' => 'Shipping'])->assertSuccessful();

        $migration = glob($this->scratch.'/database/settings/*_create_shipping_settings.php');

        expect($migration)->toHaveCount(1);

        $files = [
            $this->scratch.'/Settings/ShippingSettings.php',
            $this->scratch.'/Filament/Settings/Tabs/ShippingTab.php',
            $migration[0],
            $this->scratch.'/lang/en/settings/shipping.php',
            $this->scratch.'/lang/ar/settings/shipping.php',
        ];

        foreach ($files as $file) {
            expect(lint($file))->toBeTrue("{$file} is not valid PHP")
                ->and(File::get($file))->toContain('declare(strict_types=1);');
        }

        expect(File::get($files[0]))->toContain('final class ShippingSettings extends Settings')
            ->toContain("return 'shipping';")
            ->and(File::get($files[1]))->toContain('final class ShippingTab extends SettingsTab')
            ->toContain('return ShippingSettings::class;')
            ->and(File::get($files[2]))->toContain("\$this->migrator->add('shipping.example');")
            ->and(require $files[3])->toBe(require $files[4]);
    });

    it('accepts the name with a Tab or Settings suffix, and snake-cases the group', function (): void {
        $this->artisan('make:settings-tab', ['name' => 'LoyaltyProgramTab'])->assertSuccessful();

        expect(File::exists($this->scratch.'/Settings/LoyaltyProgramSettings.php'))->toBeTrue()
            ->and(File::get($this->scratch.'/Settings/LoyaltyProgramSettings.php'))->toContain("return 'loyalty_program';")
            ->and(File::exists($this->scratch.'/lang/en/settings/loyalty_program.php'))->toBeTrue();
    });

    it('refuses a name it cannot use as a class', function (): void {
        $this->artisan('make:settings-tab', ['name' => '123 bad'])->assertFailed();

        expect(File::exists($this->scratch.'/Settings'))->toBeFalse();
    });

    it('writes nothing when one of the files already exists', function (): void {
        File::ensureDirectoryExists($this->scratch.'/Settings');
        File::put($this->scratch.'/Settings/ShippingSettings.php', '<?php // mine');

        $this->artisan('make:settings-tab', ['name' => 'Shipping'])->assertFailed();

        expect(File::get($this->scratch.'/Settings/ShippingSettings.php'))->toBe('<?php // mine')
            ->and(File::exists($this->scratch.'/Filament/Settings/Tabs/ShippingTab.php'))->toBeFalse();
    });
});

describe('dash:seo', function (): void {
    beforeEach(function (): void {
        $this->app->useDatabasePath($this->scratch.'/database');
        $this->app->useLangPath($this->scratch.'/lang');
    });

    it('writes the SEO tab, its settings, the component and both translations', function (): void {
        $this->artisan('dash:seo', ['--no-migrate' => true])->assertSuccessful();

        $files = [
            $this->scratch.'/Settings/SeoSettings.php',
            $this->scratch.'/Filament/Settings/Tabs/SeoTab.php',
            $this->scratch.'/View/Components/Seo.php',
            ...glob($this->scratch.'/database/settings/*_create_seo_settings.php'),
            $this->scratch.'/lang/en/settings/seo.php',
            $this->scratch.'/lang/ar/settings/seo.php',
        ];

        expect($files)->toHaveCount(6);

        foreach ($files as $file) {
            expect(lint($file))->toBeTrue("{$file} is not valid PHP");
        }

        expect(array_keys(Arr::dot(require $files[4])))->toBe(array_keys(Arr::dot(require $files[5])));
    });

    it('leaves an existing setup alone', function (): void {
        $this->artisan('dash:seo', ['--no-migrate' => true])->assertSuccessful();
        File::put($this->scratch.'/Settings/SeoSettings.php', '<?php // edited');

        $this->artisan('dash:seo', ['--no-migrate' => true])
            ->expectsOutputToContain('already set up')
            ->assertSuccessful();

        expect(File::get($this->scratch.'/Settings/SeoSettings.php'))->toBe('<?php // edited')
            ->and(glob($this->scratch.'/database/settings/*_create_seo_settings.php'))->toHaveCount(1);
    });

    it('rewrites the files, keeping one migration, with --force', function (): void {
        $this->artisan('dash:seo', ['--no-migrate' => true])->assertSuccessful();
        File::put($this->scratch.'/Settings/SeoSettings.php', '<?php // edited');

        $this->artisan('dash:seo', ['--no-migrate' => true, '--force' => true])->assertSuccessful();

        expect(File::get($this->scratch.'/Settings/SeoSettings.php'))->toContain('final class SeoSettings')
            ->and(glob($this->scratch.'/database/settings/*_create_seo_settings.php'))->toHaveCount(1);
    });

    it('refuses to write source files in production', function (): void {
        app()->detectEnvironment(fn (): string => 'production');

        $this->artisan('dash:seo')->assertFailed();

        expect(File::exists($this->scratch.'/Settings/SeoSettings.php'))->toBeFalse();
    });
});

describe('the code dash:seo writes', function (): void {
    it('works: settings with defaults, public values and head tags from <x-seo>', function (): void {
        // Migrate the test database while database_path() is still the real one.
        $appName = resolve(GeneralSettings::class)->app_name;

        $this->app->useDatabasePath($this->scratch.'/database');
        $this->app->useLangPath($this->scratch.'/lang');

        $this->artisan('dash:seo', ['--no-migrate' => true])->assertSuccessful();

        // Loaded by hand: Composer's autoloader only knows the real app/.
        require_once $this->scratch.'/Settings/SeoSettings.php';
        require_once $this->scratch.'/Filament/Settings/Tabs/SeoTab.php';
        require_once $this->scratch.'/View/Components/Seo.php';
        (require glob($this->scratch.'/database/settings/*_create_seo_settings.php')[0])->up();

        $seo = resolve(SeoSettings::class);

        expect($seo->index)->toBeTrue()
            ->and($seo->title_separator)->toBe('|')
            ->and($seo->robots())->toBe('index, follow')
            ->and($seo->title('Pricing'))->toBe('Pricing | '.$appName)
            ->and(new SeoTab()->key())->toBe('seo')
            ->and(resolve(PublicSettingsService::class)->all())->toHaveKey('seo');

        $seo->site_name = 'Acme';
        $seo->description = 'Fast "quiet" <secure>';
        $seo->index = false;
        $seo->google_analytics_id = 'G-ABC123XYZ';
        $seo->save();

        $html = Blade::render('<x-seo title="Pricing" />');

        expect($html)->toContain('<title>Pricing | Acme</title>')
            ->toContain('<meta name="robots" content="noindex, follow">')
            ->toContain('<meta property="og:title" content="Pricing | Acme">')
            // Escaped, so a description cannot break out of the attribute.
            ->toContain('content="Fast &quot;quiet&quot; &lt;secure&gt;"')
            ->toContain('gtag/js?id=G-ABC123XYZ')
            ->not->toContain('<secure>')
            ->not->toContain('googletagmanager.com/gtm.js');
    })->skip(
        // This loads the generated classes under their real names, which
        // a project that has run dash:seo already has.
        fn (): bool => file_exists(base_path('app/Settings/SeoSettings.php')),
        'SEO is installed in this project, so its own code is what runs.',
    );
});
