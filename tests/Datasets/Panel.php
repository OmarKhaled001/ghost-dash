<?php

declare(strict_types=1);

use Tests\Support\Panel;

/*
| Every resource and page registered on the admin panel, discovered rather
| than listed, so a new module is covered by the authorization tests the
| moment it exists.
*/

dataset('panel resources', fn (): array => collect(Panel::entities()['resources'])
    ->mapWithKeys(fn (string $resource): array => [class_basename($resource) => [$resource]])
    ->all());

dataset('gated panel pages', fn (): array => collect(Panel::entities()['pages'])
    ->reject(fn (string $page): bool => in_array($page, Panel::OPEN_PAGES, true))
    ->mapWithKeys(fn (string $page): array => [class_basename($page) => [$page]])
    ->all());
