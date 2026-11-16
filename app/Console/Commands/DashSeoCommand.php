<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Generators\StubWriter;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'dash:seo', description: 'Add SEO to the project: a settings tab, its settings and an <x-seo> component for page heads')]
#[Signature('dash:seo
        {--no-migrate : Write the files but do not run the settings migration}
        {--force : Overwrite the SEO files if they already exist}')]
final class DashSeoCommand extends Command
{
    public static function installed(): bool
    {
        return file_exists(app_path('Settings/SeoSettings.php'));
    }

    public function handle(StubWriter $writer): int
    {
        // It writes source code, which belongs in the repository and is
        // deployed like any other change, not created on a server.
        if ($this->laravel->isProduction()) {
            $this->components->error('dash:seo writes source files. Run it while developing, commit the result and deploy it.');

            return self::FAILURE;
        }

        if (self::installed() && ! $this->option('force')) {
            $this->components->info('SEO is already set up. Use --force to write its files again.');

            return self::SUCCESS;
        }

        try {
            $written = $writer->write(
                [
                    app_path('Settings/SeoSettings.php') => 'seo/settings.stub',
                    app_path('Filament/Settings/Tabs/SeoTab.php') => 'seo/tab.stub',
                    app_path('View/Components/Seo.php') => 'seo/component.stub',
                    $this->migrationPath() => 'seo/migration.stub',
                    lang_path('en/settings/seo.php') => 'seo/lang.en.stub',
                    lang_path('ar/settings/seo.php') => 'seo/lang.ar.stub',
                ],
                ['{{ namespace }}' => rtrim($this->laravel->getNamespace(), '\\')],
                force: true,
            );
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        foreach ($written as $path) {
            $this->components->info("[{$path}] created.");
        }

        if (! $this->option('no-migrate')) {
            $this->call('migrate', ['--force' => true]);
        }

        $this->components->bulletList([
            'The SEO tab is on the Settings page.',
            'In a Blade layout, put <x-seo title="Page title" description="..." /> inside <head>.',
            'A separate frontend reads the same values from GET /api/v1/settings, under "seo".',
        ]);

        return self::SUCCESS;
    }

    /**
     * The existing migration when --force rewrites the files, so the
     * settings are not added twice under two migration names.
     */
    private function migrationPath(): string
    {
        $existing = glob(database_path('settings/*_create_seo_settings.php')) ?: [];

        return $existing[0] ?? database_path('settings/'.date('Y_m_d_His').'_create_seo_settings.php');
    }
}
