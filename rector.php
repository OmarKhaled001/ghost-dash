<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php81\Rector\MethodCall\SpatieEnumMethodCallToEnumConstRector;
use RectorLaravel\Rector\Coalesce\ApplyDefaultInsteadOfNullCoalesceRector;
use RectorLaravel\Rector\FuncCall\UnlinkFuncCallToFileFacadeDeleteRector;
use RectorLaravel\Set\LaravelLevelSetList;
use RectorLaravel\Set\LaravelSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/bootstrap/app.php',
        __DIR__.'/config',
        __DIR__.'/database',
        __DIR__.'/routes',
        __DIR__.'/tests',
    ])
    ->withPhpSets(php84: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        earlyReturn: true,
    )
    ->withSets([
        LaravelLevelSetList::UP_TO_LARAVEL_130,
        LaravelSetList::LARAVEL_CODE_QUALITY,
        LaravelSetList::LARAVEL_COLLECTION,
        LaravelSetList::LARAVEL_TYPE_DECLARATIONS,
    ])
    ->withSkip([
        // Rewrites `config('key') ?? 'x'` to `config('key', 'x')`. Those are
        // not the same: the default only applies when the key is missing,
        // not when it is present and null, which is how several package
        // configs (spatie/laravel-settings among them) spell "use the
        // default". The rewrite turned a table name into an empty string.
        ApplyDefaultInsteadOfNullCoalesceRector::class,

        // Rewrites `Status::ok()` to `Status::OK`, assuming every spatie/enum
        // class has been converted to a native enum. spatie/laravel-health's
        // Status has not, so the rewritten code refers to a constant that
        // does not exist.
        SpatieEnumMethodCallToEnumConstRector::class,

        // Rewrites unlink() to File::delete(). Plain unit tests run without
        // an application, where a facade has nothing to resolve from.
        UnlinkFuncCallToFileFacadeDeleteRector::class => [
            __DIR__.'/tests/Unit',
        ],
    ])
    ->withImportNames(importShortClasses: false, removeUnusedImports: true);
