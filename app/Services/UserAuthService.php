<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\IssuedToken;
use App\Enums\TokenAbility;
use App\Models\User;
use App\Repositories\Contracts\UserRepository;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use SensitiveParameter;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Authentication for end users over the API. Every event that matters for
 * the audit trail is dispatched from here with the "sanctum" guard, and the
 * activity log listens for those events.
 */
final readonly class UserAuthService
{
    private const string GUARD = 'sanctum';

    public function __construct(
        private UserRepository $users,
        private UserService $userService,
        private Hasher $hasher,
    ) {}

    /**
     * @param  array{name: string, email: string, password: string}  $data
     * @return array{user: User, token: IssuedToken}
     */
    public function register(#[SensitiveParameter] array $data, string $deviceName): array
    {
        $user = $this->userService->create($data);
        $token = $this->issueToken($user, $deviceName);

        // Registered sends the verification email. If the mailer is down
        // the account still exists and the user can ask for the email
        // again, so a mail failure is reported rather than turned into a
        // failed registration that cannot be retried ("email taken").
        try {
            event(new Registered($user));
        } catch (Throwable $exception) {
            report($exception);
        }

        return ['user' => $user, 'token' => $token];
    }

    /**
     * @param  list<string>|null  $abilities  Null issues a token with every ability.
     * @return array{user: User, token: IssuedToken}
     */
    public function login(string $email, #[SensitiveParameter] string $password, string $deviceName, ?array $abilities = null): array
    {
        $user = $this->users->findByEmail($email);

        // A hash is checked even when the address is unknown, so that the
        // response time does not reveal which addresses have accounts.
        $valid = $this->hasher->check($password, $user instanceof User ? $user->password : $this->decoyHash());

        if (! $user instanceof User || ! $valid) {
            event(new Failed(self::GUARD, $user, ['email' => $email]));

            throw ValidationException::withMessages(['email' => [__('auth.failed')]]);
        }

        event(new Login(self::GUARD, $user, false));

        return ['user' => $user, 'token' => $this->issueToken($user, $deviceName, $abilities)];
    }

    /**
     * Revoke the token the request was made with.
     */
    public function logout(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        event(new Logout(self::GUARD, $user));
    }

    /**
     * Revoke every token of the user: "sign out everywhere".
     */
    public function logoutEverywhere(User $user): void
    {
        $user->tokens()->delete();

        event(new Logout(self::GUARD, $user));
    }

    /**
     * Whether or not the address belongs to an account, the caller learns
     * nothing: the outcome is deliberately not returned, and the work is
     * done after the response has gone out. Creating a token and handing a
     * mail to the mailer takes far longer than finding no account, and that
     * difference alone would tell a caller which addresses exist.
     */
    public function sendPasswordResetLink(string $email): void
    {
        defer(fn (): string => Password::broker('users')->sendResetLink(['email' => $email]));
    }

    /**
     * @param  array{email: string, token: string, password: string}  $data
     */
    public function resetPassword(#[SensitiveParameter] array $data): void
    {
        $status = Password::broker('users')->reset(
            ['email' => $data['email'], 'token' => $data['token'], 'password' => $data['password']],
            function (User $user, #[SensitiveParameter] string $password): void {
                DB::transaction(function () use ($user, $password): void {
                    $this->users->update($user, [
                        'password' => $password,
                        'remember_token' => Str::random(60),
                    ]);

                    // Whoever knew the old password may hold tokens.
                    $user->tokens()->delete();
                });

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            // One message for "no such user" and "bad token": telling them
            // apart would turn this endpoint into an account lookup.
            throw ValidationException::withMessages(['email' => [__('passwords.token')]]);
        }
    }

    /**
     * Change the password and sign out every other device.
     */
    public function updatePassword(User $user, #[SensitiveParameter] string $password): void
    {
        DB::transaction(function () use ($user, $password): void {
            $this->users->update($user, ['password' => $password]);

            $current = $user->currentAccessToken();

            $user->tokens()
                ->when($current instanceof PersonalAccessToken, fn ($tokens) => $tokens->whereKeyNot($current->getKey()))
                ->delete();
        });
    }

    /**
     * Confirm an address from the signed link in the verification email.
     * The route's "signed" middleware has already checked the signature and
     * expiry; the hash ties the link to the address it was sent to.
     *
     * @return bool False when the address was verified already.
     */
    public function verifyEmail(int $userId, string $hash): bool
    {
        $user = $this->users->find($userId);

        if (! $user instanceof User || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            throw new AuthorizationException;
        }

        if ($user->hasVerifiedEmail()) {
            return false;
        }

        $user->markEmailAsVerified();

        event(new Verified($user));

        return true;
    }

    /**
     * @return bool False when there was nothing to send: already verified.
     */
    public function resendVerification(User $user): bool
    {
        if ($user->hasVerifiedEmail()) {
            return false;
        }

        $user->sendEmailVerificationNotification();

        return true;
    }

    /**
     * @param  list<string>|null  $abilities
     */
    private function issueToken(User $user, string $deviceName, ?array $abilities = null): IssuedToken
    {
        // Only abilities that exist are ever granted, whatever was asked for.
        $abilities = blank($abilities)
            ? TokenAbility::values()
            : array_values(array_intersect(TokenAbility::values(), $abilities));

        $minutes = (int) config('sanctum.expiration');
        $expiresAt = $minutes > 0 ? CarbonImmutable::now()->addMinutes($minutes) : null;

        $token = $user->createToken($deviceName, $abilities, $expiresAt);

        return new IssuedToken($token->plainTextToken, $abilities, $expiresAt);
    }

    /**
     * A valid hash of a password nobody has. It is kept in the cache:
     * making one on every request would cost an unknown address two hash
     * operations where a known one costs one, which is the very difference
     * the decoy exists to hide. The key follows the hashing settings, so a
     * decoy of another cost is not reused after they change.
     */
    private function decoyHash(): string
    {
        $key = 'auth.decoy-hash.'.hash('xxh128', (string) json_encode(config('hashing')));

        return Cache::rememberForever($key, fn (): string => $this->hasher->make(Str::random(40)));
    }
}
