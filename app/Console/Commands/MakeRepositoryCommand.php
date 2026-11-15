<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'make:repository', description: 'Create a repository contract, its Eloquent implementation and the container binding')]
#[Signature('make:repository
        {name : The model the repository is for, e.g. Invoice}
        {--model= : The model class, when it is not App\Models\<name>}
        {--force : Overwrite the repository files if they already exist}')]
final class MakeRepositoryCommand extends Command
{
    public function handle(Filesystem $files): int
    {
        $name = Str::of((string) $this->argument('name'))
            ->replace('/', '\\')
            ->classBasename()
            ->beforeLast('Repository')
            ->studly()
            ->toString();

        if ($name === '') {
            $this->components->error('Give the repository a name, for example: php artisan make:repository Invoice');

            return self::FAILURE;
        }

        $root = rtrim($this->laravel->getNamespace(), '\\');
        $modelFqcn = ltrim((string) ($this->option('model') ?: "{$root}\\Models\\{$name}"), '\\');

        $replacements = [
            '{{ model }}' => class_basename($modelFqcn),
            '{{ modelFqcn }}' => $modelFqcn,
            '{{ variable }}' => Str::camel(class_basename($modelFqcn)),
            '{{ contract }}' => "{$name}Repository",
            '{{ contractFqcn }}' => "{$root}\\Repositories\\Contracts\\{$name}Repository",
        ];

        $targets = [
            [
                'stub' => 'repository.contract.stub',
                'path' => app_path("Repositories/Contracts/{$name}Repository.php"),
                'namespace' => "{$root}\\Repositories\\Contracts",
                'class' => "{$name}Repository",
            ],
            [
                'stub' => 'repository.eloquent.stub',
                'path' => app_path("Repositories/Eloquent/Eloquent{$name}Repository.php"),
                'namespace' => "{$root}\\Repositories\\Eloquent",
                'class' => "Eloquent{$name}Repository",
            ],
        ];

        foreach ($targets as $target) {
            if ($files->exists($target['path']) && ! $this->option('force')) {
                $this->components->error("[{$target['path']}] already exists. Use --force to overwrite it.");

                return self::FAILURE;
            }
        }

        foreach ($targets as $target) {
            $contents = strtr($files->get(base_path('stubs/'.$target['stub'])), [
                ...$replacements,
                '{{ namespace }}' => $target['namespace'],
                '{{ class }}' => $target['class'],
            ]);

            $files->ensureDirectoryExists(dirname($target['path']));
            $files->put($target['path'], $this->sortImports($contents));

            $this->components->info("[{$target['path']}] created.");
        }

        $bound = $this->bind(
            $files,
            "{$root}\\Repositories\\Contracts\\{$name}Repository",
            "{$root}\\Repositories\\Eloquent\\Eloquent{$name}Repository",
        );

        $bound
            ? $this->components->info("Bound {$name}Repository to Eloquent{$name}Repository in RepositoryServiceProvider.")
            : $this->components->warn('RepositoryServiceProvider was not changed: add the binding to its $bindings array yourself.');

        if (! class_exists($modelFqcn)) {
            $this->components->warn("[{$modelFqcn}] does not exist yet. Create it, or pass --model= with the right class.");
        }

        return self::SUCCESS;
    }

    /**
     * Add the binding to RepositoryServiceProvider::$bindings, so that a new
     * repository is injectable the moment it is generated.
     */
    private function bind(Filesystem $files, string $contract, string $implementation): bool
    {
        $path = app_path('Providers/RepositoryServiceProvider.php');

        if (! $files->exists($path)) {
            return false;
        }

        $source = $files->get($path);
        $entry = class_basename($contract).'::class => '.class_basename($implementation).'::class,';

        if (str_contains($source, $entry)) {
            return true;
        }

        // The closing bracket of the $bindings array, wherever its entries end.
        $updated = preg_replace(
            '/(public array \$bindings = \[.*?)(\n    \];)/s',
            '$1'."\n        ".$entry.'$2',
            $source,
            1,
            $count,
        );

        if ($updated === null || $count !== 1) {
            return false;
        }

        $updated = preg_replace(
            '/^(use [^\n]+;\n)/m',
            "use {$contract};\nuse {$implementation};\n".'$1',
            $updated,
            1,
        );

        if ($updated === null) {
            return false;
        }

        // Written to a temporary file and moved into place, so another
        // process never autoloads a half-written provider.
        $temporary = $path.'.'.Str::random(8).'.tmp';
        $files->put($temporary, $this->sortImports($updated));
        $files->move($temporary, $path);

        return true;
    }

    /**
     * Keep the generated code passing Pint without a second step: its
     * ordered_imports rule wants the use statements in alphabetical order.
     */
    private function sortImports(string $source): string
    {
        if (preg_match('/(?:^use [^\n]+;\n)+/m', $source, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return $source;
        }

        [$block, $offset] = $match[0];

        $imports = array_unique(array_filter(explode("\n", trim($block))));
        usort($imports, fn (string $a, string $b): int => strcasecmp(rtrim($a, ';'), rtrim($b, ';')));

        return substr_replace($source, implode("\n", $imports)."\n", $offset, strlen($block));
    }
}
