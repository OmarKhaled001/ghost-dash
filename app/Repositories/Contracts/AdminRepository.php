<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Builder;

interface AdminRepository
{
    /**
     * Admins the given admin is allowed to know about. Everyone except a
     * super admin gets a query that leaves super admins out.
     *
     * @return Builder<Admin>
     */
    public function queryVisibleTo(Admin $actor): Builder;

    public function find(int $id): ?Admin;

    public function findByEmail(string $email): ?Admin;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Admin;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Admin $admin, array $attributes): Admin;

    public function delete(Admin $admin): void;

    public function superAdminExists(): bool;

    /**
     * Count active super admins other than the given one, locking the rows
     * so two concurrent requests cannot each remove "the other" last one.
     * Must be called inside a transaction.
     */
    public function countOtherActiveSuperAdminsForUpdate(Admin $except): int;
}
