<?php

declare(strict_types=1);

use App\Filament\Settings\SettingsTab;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AuthenticatedRequest;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

arch('no debugging calls are left behind')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r', 'ddd'])
    ->not->toBeUsed();

arch('every application file declares strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('database and test code declares strict types')
    ->expect(['Database', 'Tests'])
    ->toUseStrictTypes();

arch('services are final and named after what they are')
    ->expect('App\Services')
    ->toBeClasses()
    ->toBeFinal()
    ->toHaveSuffix('Service');

arch('repository contracts are interfaces')
    ->expect('App\Repositories\Contracts')
    ->toBeInterfaces()
    ->toHaveSuffix('Repository');

arch('eloquent repositories implement a contract')
    ->expect('App\Repositories\Eloquent')
    ->toBeClasses()
    ->toBeFinal()
    ->toHavePrefix('Eloquent')
    ->toHaveSuffix('Repository');

arch('policies are final and named after what they are')
    ->expect('App\Policies')
    ->toBeClasses()
    ->toBeFinal()
    ->toHaveSuffix('Policy');

arch('enums are backed')
    ->expect('App\Enums')
    ->toBeStringBackedEnums();

arch('application classes are final unless they are meant to be extended')
    ->expect('App')
    ->classes()
    ->toBeFinal()
    ->ignoring([
        Controller::class,
        // Base for the form requests of endpoints behind auth:sanctum.
        AuthenticatedRequest::class,
        // Base for the tabs of the Settings page.
        SettingsTab::class,
    ]);

arch('controllers stay thin: no models, query builders or the DB facade')
    ->expect('App\Http\Controllers')
    ->not->toUse([
        'App\Models',
        'App\Repositories',
        DB::class,
        Builder::class,
        Illuminate\Database\Query\Builder::class,
    ]);

arch('filament resources and pages do not write to the database themselves')
    ->expect('App\Filament')
    ->not->toUse(DB::class);

arch('form requests and API resources are named after what they are')
    ->expect('App\Http\Requests')
    ->toHaveSuffix('Request')
    ->and('App\Http\Resources')
    ->toHaveSuffix('Resource');

arch('API controllers answer through the envelope, never with a bare response')
    ->expect('App\Http\Controllers\Api')
    ->toUse(ApiResponse::class)
    ->toHaveSuffix('Controller');
