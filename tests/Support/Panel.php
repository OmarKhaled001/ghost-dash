<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Filament\Contracts\GatedByPermission;
use App\Models\Admin;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Bootstrap\HandleExceptions;
use RuntimeException;

/**
 * What the admin panel registers, and which permission guards each entry.
 */
final class Panel
{
    /**
     * Pages every admin who can enter the panel may open. Anything else
     * must be gated by a permission, or the authorization test fails.
     */
    public const array OPEN_PAGES = [
        Dashboard::class,
    ];

    /** @var array{resources: list<class-string>, pages: list<class-string>}|null */
    private static ?array $entities = null;

    /**
     * Pest builds datasets before any test (and therefore any application)
     * exists, so the panel is read from a short-lived application instance.
     *
     * @return array{resources: list<class-string>, pages: list<class-string>}
     */
    public static function entities(): array
    {
        if (self::$entities !== null) {
            return self::$entities;
        }

        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        $panel = Filament::getPanel(Admin::PANEL);

        self::$entities = [
            'resources' => array_values($panel->getResources()),
            'pages' => array_values($panel->getPages()),
        ];

        $app->flush();
        HandleExceptions::flushState();

        return self::$entities;
    }

    /**
     * @param  class-string  $resource
     */
    public static function permissionForResource(string $resource): string
    {
        if (is_subclass_of($resource, GatedByPermission::class)) {
            return $resource::gatePermission();
        }

        Filament::setCurrentPanel(Filament::getPanel(Admin::PANEL));

        return FilamentShield::getResources()[$resource]['permissions']['viewAny']['key']
            ?? throw new RuntimeException("[{$resource}] has no permission gate. Let Shield manage it or implement GatedByPermission.");
    }

    /**
     * @param  class-string  $page
     */
    public static function permissionForPage(string $page): string
    {
        if (is_subclass_of($page, GatedByPermission::class)) {
            return $page::gatePermission();
        }

        Filament::setCurrentPanel(Filament::getPanel(Admin::PANEL));

        $permissions = FilamentShield::getPages()[$page]['permissions'] ?? [];

        return array_key_first($permissions)
            ?? throw new RuntimeException("[{$page}] has no permission gate. Use HasPageShield, implement GatedByPermission, or add it to Panel::OPEN_PAGES on purpose.");
    }
}
