<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Admin;
use App\Models\Role;
use App\Repositories\Contracts\AdminRepository;
use Illuminate\Database\Eloquent\Builder;

final class EloquentAdminRepository implements AdminRepository
{
    public function queryVisibleTo(Admin $actor): Builder
    {
        $query = Admin::query();

        if ($actor->isSuperAdmin()) {
            return $query;
        }

        return $query->whereDoesntHave(
            'roles',
            fn (Builder $roles): Builder => $roles->where('name', Role::SUPER_ADMIN),
        );
    }

    public function find(int $id): ?Admin
    {
        return Admin::query()->find($id);
    }

    public function findByEmail(string $email): ?Admin
    {
        return Admin::query()->where('email', $email)->first();
    }

    public function create(array $attributes): Admin
    {
        $admin = new Admin;
        $admin->forceFill($attributes)->save();

        return $admin->refresh();
    }

    public function update(Admin $admin, array $attributes): Admin
    {
        $admin->forceFill($attributes)->save();

        return $admin;
    }

    public function delete(Admin $admin): void
    {
        $admin->delete();
    }

    public function superAdminExists(): bool
    {
        return $this->superAdmins()->exists();
    }

    public function countOtherActiveSuperAdminsForUpdate(Admin $except): int
    {
        return $this->superAdmins()
            ->where('is_active', true)
            ->whereKeyNot($except->getKey())
            ->lockForUpdate()
            ->count();
    }

    /**
     * @return Builder<Admin>
     */
    private function superAdmins(): Builder
    {
        return Admin::query()->whereHas(
            'roles',
            fn (Builder $roles): Builder => $roles->where('name', Role::SUPER_ADMIN),
        );
    }
}
