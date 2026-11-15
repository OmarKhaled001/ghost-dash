<?php

declare(strict_types=1);

use App\Providers\ApiServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\RepositoryServiceProvider;

return [
    AppServiceProvider::class,
    ApiServiceProvider::class,
    RepositoryServiceProvider::class,
    AdminPanelProvider::class,
];
