<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Admin;
use App\Models\Permission;
use App\Models\Role;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Brings the permissions table in line with the code: one permission for
 * every action on every panel resource, page and widget, plus the custom
 * ones. It only inserts rows, so it is safe to run on every deployment.
 */
final readonly class PermissionSyncService
{
    public function __construct(private PermissionRegistrar $registrar) {}

    /**
     * Every permission name the application defines right now.
     *
     * @return list<string>
     */
    public function known(): array
    {
        // Shield discovers entities from the "current" panel, which is only
        // set during a panel request. Console commands and tests need it too.
        Filament::setCurrentPanel(Filament::getPanel(Admin::PANEL));

        return array_values(FilamentShield::getEntitiesPermissions() ?? []);
    }

    /**
     * @return array{created: list<string>, total: int}
     */
    public function sync(): array
    {
        $known = $this->known();

        $created = DB::transaction(function () use ($known): array {
            $existing = Permission::query()
                ->where('guard_name', Role::GUARD)
                ->pluck('name')
                ->all();

            $missing = array_values(array_diff($known, $existing));

            foreach ($missing as $name) {
                Permission::query()->create(['name' => $name, 'guard_name' => Role::GUARD]);
            }

            $superAdmin = Role::query()->firstOrCreate(['name' => Role::SUPER_ADMIN, 'guard_name' => Role::GUARD]);

            // Attach only what is missing instead of re-syncing everything,
            // so a deployment that adds one permission produces one change.
            $ungranted = Permission::query()
                ->where('guard_name', Role::GUARD)
                ->whereNotIn('id', $superAdmin->permissions()->select('permissions.id'))
                ->get();

            if ($ungranted->isNotEmpty()) {
                $superAdmin->givePermissionTo($ungranted);
            }

            return $missing;
        });

        $this->registrar->forgetCachedPermissions();

        return ['created' => $created, 'total' => count($known)];
    }
}
