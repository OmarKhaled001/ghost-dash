<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ActivityLog;
use Override;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Contracts\Role as RoleContract;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * @property int $id
 * @property string $name
 * @property string $guard_name
 */
final class Role extends SpatieRole
{
    use LogsActivity;

    public const string GUARD = 'admin';

    public const string SUPER_ADMIN = 'super_admin';

    private bool $syncingPermissions = false;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(array $attributes = [])
    {
        // Roles only exist for admins. Without this, spatie falls back to
        // the default guard ("web") whenever a caller forgets to name one.
        $attributes['guard_name'] ??= self::GUARD;

        parent::__construct($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return RoleContract|SpatieRole
     */
    public static function create(array $attributes = [])
    {
        $attributes['guard_name'] ??= self::GUARD;

        return parent::create($attributes);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName(ActivityLog::Roles)
            ->logOnly(['name', 'guard_name'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * Permissions live in a pivot table, so no model event fires when a
     * role gains or loses one. Wrapping the sync records every change of
     * what a role can do, whoever makes it.
     *
     * @param  mixed  ...$permissions
     */
    #[Override]
    public function syncPermissions(...$permissions): static
    {
        $before = $this->exists ? $this->permissions()->pluck('name')->all() : [];

        $this->syncingPermissions = true;

        try {
            parent::syncPermissions(...$permissions);
        } finally {
            $this->syncingPermissions = false;
        }

        $this->logPermissionChange($before);

        return $this;
    }

    /**
     * @param  mixed  ...$permissions
     */
    #[Override]
    public function givePermissionTo(...$permissions): static
    {
        // syncPermissions() calls this internally and logs the net result
        // itself; logging here as well would record every sync twice.
        if ($this->syncingPermissions) {
            return parent::givePermissionTo(...$permissions);
        }

        $before = $this->exists ? $this->permissions()->pluck('name')->all() : [];

        parent::givePermissionTo(...$permissions);

        $this->logPermissionChange($before);

        return $this;
    }

    public function isSuperAdmin(): bool
    {
        return $this->name === self::SUPER_ADMIN;
    }

    /**
     * @param  list<string>  $before
     */
    private function logPermissionChange(array $before): void
    {
        $after = $this->permissions()->pluck('name')->all();
        $attached = array_values(array_diff($after, $before));
        $detached = array_values(array_diff($before, $after));

        if ($attached === [] && $detached === []) {
            return;
        }

        activity(ActivityLog::Roles)
            ->performedOn($this)
            ->event('permissions_synced')
            ->withProperties(['attached' => $attached, 'detached' => $detached])
            ->log('permissions_synced');
    }
}
