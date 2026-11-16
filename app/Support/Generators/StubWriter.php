<?php

declare(strict_types=1);

namespace App\Support\Generators;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;

/**
 * Writes a set of files from stubs in stubs/, all or none: when one target
 * already exists (and overwriting was not asked for), nothing is written.
 */
final readonly class StubWriter
{
    public function __construct(private Filesystem $files) {}

    /**
     * @param  array<string, string>  $targets  Path to write => stub path relative to stubs/.
     * @param  array<string, string>  $replacements  Placeholder => value, applied to every stub.
     * @return list<string> The paths that were written.
     *
     * @throws RuntimeException When a target exists and $force is false.
     */
    public function write(array $targets, array $replacements, bool $force = false): array
    {
        if (! $force) {
            foreach (array_keys($targets) as $path) {
                if ($this->files->exists($path)) {
                    throw new RuntimeException("[{$path}] already exists. Use --force to overwrite it.");
                }
            }
        }

        $written = [];

        foreach ($targets as $path => $stub) {
            $this->files->ensureDirectoryExists(dirname($path));
            $this->files->put($path, strtr($this->files->get(base_path('stubs/'.$stub)), $replacements));
            $written[] = $path;
        }

        return $written;
    }
}
