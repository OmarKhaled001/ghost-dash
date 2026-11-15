<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

afterEach(function (): void {
    putenv('GHOSTDASH_SKIP_AUTO_INSTALL');
    putenv('COMPOSER_NO_INTERACTION');
    putenv('LARAVEL_INSTALLER_DEFER_HOOKS');
});

it('prints the next step instead of starting the installer without a terminal', function (): void {
    // The test runner has no TTY, which is the situation in CI and under
    // the Laravel installer.
    $this->artisan('dash:post-create')
        ->expectsOutputToContain('cd '.basename(base_path()).' && php artisan dash:install')
        ->assertSuccessful();
});

it('stays out of the way when the bootstrap script asks it to', function (): void {
    putenv('GHOSTDASH_SKIP_AUTO_INSTALL=1');

    $this->artisan('dash:post-create')
        ->expectsOutputToContain('php artisan dash:install')
        ->assertSuccessful();
});

it('stays out of the way when Composer runs with --no-interaction', function (): void {
    putenv('COMPOSER_NO_INTERACTION=1');

    $this->artisan('dash:post-create')
        ->expectsOutputToContain('php artisan dash:install')
        ->assertSuccessful();
});

it('is hidden from the command list', function (): void {
    expect(Artisan::all()['dash:post-create']->isHidden())->toBeTrue();
});

it('is what composer create-project runs, and it never migrates', function (): void {
    $scripts = json_decode((string) file_get_contents(base_path('composer.json')), true)['scripts'];

    expect($scripts['post-create-project-cmd'])->toBe([
        // Composer kills a script after 300 seconds by default, which an
        // installer waiting for answers would easily exceed.
        'Composer\\Config::disableProcessTimeout',
        '@php artisan key:generate --ansi',
        '@php artisan dash:post-create --ansi',
    ])
        ->and($scripts['post-root-package-install'])->toBe(["@php -r \"file_exists('.env') || copy('.env.example', '.env');\""])
        ->and($scripts['setup'])->toBe(['composer install', '@php artisan dash:install'])
        ->and(json_encode($scripts))->not->toContain('sqlite');
});

it('waits when the Laravel installer asks kits to defer their hooks', function (): void {
    putenv('LARAVEL_INSTALLER_DEFER_HOOKS=1');

    $this->artisan('dash:post-create')
        ->expectsOutputToContain('php artisan dash:install')
        ->assertSuccessful();
});

it('registers itself as a Laravel installer hook', function (): void {
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);

    expect($composer['extra']['laravel']['installer']['post-create-project'])->toBe(['@php artisan dash:post-create --ansi']);
});

it('keeps local secrets and build output out of a path-repository copy', function (): void {
    // Composer mirrors a path repository by .gitattributes, not .gitignore.
    $attributes = (string) file_get_contents(base_path('.gitattributes'));

    foreach (['/.env', '/.env.testing', '/vendor', '/node_modules', '/public/build'] as $path) {
        expect($attributes)->toContain("{$path} export-ignore");
    }
});

it('keeps kit-only files out of projects created from the kit', function (): void {
    $attributes = (string) file_get_contents(base_path('.gitattributes'));

    foreach (['/install.sh', '/install.ps1', '/.github/workflows/install-e2e.yml'] as $path) {
        expect($attributes)->toContain("{$path} export-ignore");
    }
});
