<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\SocialProvider;
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

    public function findBySocialAccount(SocialProvider $provider, string $providerUserId): ?User
    {
        return User::query()
            ->whereHas('socialAccounts', fn (Builder $accounts): Builder => $accounts
                ->where('provider', $provider->value)
                ->where('provider_user_id', $providerUserId))
            ->first();
    }

    public function linkSocialAccount(User $user, SocialProvider $provider, string $providerUserId): void
    {
        $user->socialAccounts()->create([
            'provider' => $provider,
            'provider_user_id' => $providerUserId,
        ]);
    }
}
