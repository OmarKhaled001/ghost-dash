<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepository;
use Illuminate\Database\Eloquent\Builder;

final class EloquentUserRepository implements UserRepository
{
    public function query(): Builder
    {
        return User::query();
    }

    public function find(int $id): ?User
    {
        return User::query()->find($id);
    }

    public function findByEmail(string $email): ?User
    {
        return User::query()->where('email', $email)->first();
    }

    public function create(array $attributes): User
    {
        $user = new User;
        $user->forceFill($attributes)->save();

        return $user->refresh();
    }

    public function update(User $user, array $attributes): User
    {
        $user->forceFill($attributes)->save();

        return $user;
    }

    public function delete(User $user): void
    {
        $user->delete();
    }
}
