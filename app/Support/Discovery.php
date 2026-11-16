<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

final class Discovery
{
    /**
     * The concrete classes in a directory (recursively) that extend or
     * implement the given type. Dropping a file into the directory is all it
     * takes to register something, which is the point.
     *
     * @template T of object
     *
     * @param  class-string<T>  $type
     * @return list<class-string<T>>
     */
    public static function classes(string $directory, string $namespace, string $type): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $classes = [];

        foreach (Finder::create()->files()->in($directory)->name('*.php')->sortByName() as $file) {
            $class = $namespace.'\\'.Str::of($file->getRelativePathname())
                ->beforeLast('.php')
                ->replace('/', '\\')
                ->toString();

            if (! class_exists($class) || ! is_subclass_of($class, $type)) {
                continue;
            }

            if (new ReflectionClass($class)->isAbstract()) {
                continue;
            }

            $classes[] = $class;
        }

        return $classes;
    }
}
