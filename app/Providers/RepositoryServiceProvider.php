<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\Contracts\AdminRepository;
use App\Repositories\Contracts\UserRepository;
use App\Repositories\Eloquent\EloquentAdminRepository;
use App\Repositories\Eloquent\EloquentUserRepository;
use Illuminate\Support\ServiceProvider;

final class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Repository interfaces and the implementation each one resolves to.
     * `php artisan make:repository` appends to this list.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        AdminRepository::class => EloquentAdminRepository::class,
        UserRepository::class => EloquentUserRepository::class,
    ];
}
