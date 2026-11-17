<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Enums\SocialProvider;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

interface UserRepository
{
    /**
     * @return Builder<User>
     */
    public function query(): Builder;

    public function find(int $id): ?User;

    public function findByEmail(string $email): ?User;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): User;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(User $user, array $attributes): User;

    public function delete(User $user): void;

    public function findBySocialAccount(SocialProvider $provider, string $providerUserId): ?User;

    public function linkSocialAccount(User $user, SocialProvider $provider, string $providerUserId): void;
}
