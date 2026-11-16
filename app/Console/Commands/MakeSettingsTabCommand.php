<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Generators\StubWriter;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'make:settings-tab', description: 'Create a tab on the Settings page: settings class, settings migration, tab and translations')]
#[Signature('make:settings-tab
        {name : The tab, e.g. Shipping}
        {--force : Overwrite the files if they already exist}')]
final class MakeSettingsTabCommand extends Command
{
    public function handle(StubWriter $writer): int
    {
        $name = Str::of((string) $this->argument('name'))
            ->replace(['/', '\\'], '')
            ->beforeLast('Settings')
            ->beforeLast('Tab')
            ->studly()
            ->toString();

        if (preg_match('/^[A-Z][A-Za-z0-9]*$/', $name) !== 1) {
            $this->components->error('Give the tab a name made of letters and digits, for example: php artisan make:settings-tab Shipping');

            return self::FAILURE;
        }

        $group = Str::snake($name);

        try {
            $written = $writer->write(
                [
                    app_path("Settings/{$name}Settings.php") => 'settings/settings.stub',
                    app_path("Filament/Settings/Tabs/{$name}Tab.php") => 'settings/tab.stub',
                    database_path('settings/'.date('Y_m_d_His')."_create_{$group}_settings.php") => 'settings/migration.stub',
                    lang_path("en/settings/{$group}.php") => 'settings/lang.stub',
                    lang_path("ar/settings/{$group}.php") => 'settings/lang.stub',
                ],
                [
                    '{{ namespace }}' => rtrim($this->laravel->getNamespace(), '\\'),
                    '{{ name }}' => $name,
                    '{{ group }}' => $group,
                    '{{ label }}' => Str::headline($name),
                ],
                (bool) $this->option('force'),
            );
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        foreach ($written as $path) {
            $this->components->info("[{$path}] created.");
        }

        $this->components->bulletList([
            "Add your properties to {$name}Settings, a line per property to the settings migration, and a field per property to {$name}Tab.",
            "Translate lang/ar/settings/{$group}.php: it starts out in English.",
            'Run `php artisan migrate`. The tab then appears on the Settings page by itself.',
            "To expose values through GET /api/v1/settings, implement App\\Settings\\Contracts\\PublicSettings on {$name}Settings.",
        ]);

        return self::SUCCESS;
    }
}
