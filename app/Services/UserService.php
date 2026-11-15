<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\UserRepository;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final readonly class UserService
{
    private const array WRITABLE = ['name', 'email', 'password'];

    public function __construct(private UserRepository $users) {}

    /**
     * @param  array{name: string, email: string, password: string}  $attributes
     */
    public function create(array $attributes, bool $verified = false): User
    {
        return $this->users->create([
            ...Arr::only($attributes, self::WRITABLE),
            'email_verified_at' => $verified ? now() : null,
        ]);
    }

    /**
     * @param  array{name?: string, email?: string, password?: string|null}  $attributes
     */
    public function update(User $user, array $attributes, ?bool $verified = null): User
    {
        $attributes = Arr::only($attributes, self::WRITABLE);

        // An empty password field on the edit form means "keep the current one".
        if (blank($attributes['password'] ?? null)) {
            unset($attributes['password']);
        }

        if ($verified !== null && $verified !== $user->hasVerifiedEmail()) {
            $attributes['email_verified_at'] = $verified ? now() : null;
        }

        return DB::transaction(function () use ($user, $attributes): User {
            // An admin sets a new password when an account may be in the
            // wrong hands. Tokens issued under the old one must not survive
            // that, just as they do not survive the user's own reset.
            if (array_key_exists('password', $attributes)) {
                $user->tokens()->delete();
            }

            return $this->users->update($user, $attributes);
        });
    }

    public function delete(User $user): void
    {
        DB::transaction(function () use ($user): void {
            // API tokens have no foreign key (they are polymorphic), so they
            // would outlive the account they belong to.
            $user->tokens()->delete();

            $this->users->delete($user);
        });
    }
}
