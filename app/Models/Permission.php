<?php

declare(strict_types=1);

namespace App\Models;

use Spatie\Permission\Contracts\Permission as PermissionContract;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * @property int $id
 * @property string $name
 * @property string $guard_name
 */
final class Permission extends SpatiePermission
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(array $attributes = [])
    {
        // Permissions only exist for admins. Without this, spatie falls back
        // to the default guard ("web") whenever a caller forgets to name one.
        $attributes['guard_name'] ??= Role::GUARD;

        parent::__construct($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return PermissionContract|SpatiePermission
     */
    public static function create(array $attributes = [])
    {
        $attributes['guard_name'] ??= Role::GUARD;

        return parent::create($attributes);
    }
}
