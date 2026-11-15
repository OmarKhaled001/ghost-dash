<?php

declare(strict_types=1);

use App\Enums\ActivityLog;
use App\Filament\Pages\Auth\Login;
use App\Models\Activity;
use App\Models\User;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\Pages\PasswordReset\ResetPassword;
use Filament\Facades\Filament;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login as LoginEvent;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

use function Pest\Laravel\get;
use function Pest\Laravel\withHeader;
use function Pest\Livewire\livewire;

function authActivity(string $event): ?Activity
{
    return Activity::query()
        ->where('log_name', ActivityLog::Authentication->value)
        ->where('event', $event)
        ->latest('id')
        ->first();
}

describe('admin guard', function (): void {
    it('logs a sign-in with the guard, IP and user agent', function (): void {
        $admin = admin();

        withHeader('User-Agent', 'Pest Browser/1.0');

        livewire(Login::class)
            ->fillForm(['email' => $admin->email, 'password' => 'password'])
            ->call('authenticate');

        $activity = authActivity('login');

        expect($activity)
            ->causer_type->toBe('admin')
            ->causer_id->toBe($admin->id)
            ->subject_id->toBe($admin->id)
            ->and($activity->properties->get('guard'))->toBe('admin')
            ->and($activity->properties->get('ip'))->toBe('127.0.0.1')
            ->and($activity->properties->has('user_agent'))->toBeTrue();
    });

    it('logs a failed sign-in with the address but never the password', function (): void {
        $admin = admin();

        livewire(Login::class)
            ->fillForm(['email' => $admin->email, 'password' => 'the-wrong-password'])
            ->call('authenticate');

        $activity = authActivity('failed_login');

        expect($activity->properties->get('email'))->toBe($admin->email)
            ->and($activity->properties->get('guard'))->toBe('admin')
            ->and($activity->subject_id)->toBe($admin->id)
            ->and((string) $activity->getRawOriginal('properties'))->not->toContain('the-wrong-password');
    });

    it('logs a failed sign-in for an unknown address without a subject', function (): void {
        livewire(Login::class)
            ->fillForm(['email' => 'nobody@example.com', 'password' => 'whatever'])
            ->call('authenticate');

        expect(authActivity('failed_login'))
            ->subject_id->toBeNull()
            ->causer_id->toBeNull()
            ->and(authActivity('failed_login')->properties->get('email'))->toBe('nobody@example.com');
    });

    it('logs a lockout with the guard and the address that was tried', function (): void {
        $admin = admin();
        $component = livewire(Login::class);

        foreach (range(1, 6) as $attempt) {
            $component->fillForm(['email' => $admin->email, 'password' => 'wrong'])->call('authenticate');
        }

        $activity = authActivity('lockout');

        expect($activity)->not->toBeNull()
            ->and($activity->properties->get('guard'))->toBe('admin')
            ->and($activity->properties->get('email'))->toBe($admin->email);
    });

    it('logs a sign-out', function (): void {
        $admin = actingAsAdmin();

        $this->post(panelUrl('logout'));

        expect(authActivity('logout'))
            ->causer_id->toBe($admin->id)
            ->and(authActivity('logout')->properties->get('guard'))->toBe('admin');
    });

    it('logs a password reset', function (): void {
        $admin = admin();
        $token = Password::broker('admins')->createToken($admin);

        livewire(ResetPassword::class, ['email' => $admin->email, 'token' => $token])
            ->fillForm(['password' => 'A-brand-new-Passw0rd', 'passwordConfirmation' => 'A-brand-new-Passw0rd'])
            ->call('resetPassword');

        expect(authActivity('password_reset'))
            ->subject_type->toBe('admin')
            ->subject_id->toBe($admin->id)
            ->and(authActivity('password_reset')->properties->get('guard'))->toBe('admin');
    });

    it('logs an email verification', function (): void {
        $admin = actingAsAdmin(admin(['email_verified_at' => null]));

        get(Filament::getPanel('admin')->getVerifyEmailUrl($admin));

        expect(authActivity('email_verified')->subject_id)->toBe($admin->id);
    });

    it('logs multi-factor authentication being enabled and disabled', function (): void {
        $admin = admin();

        $admin->saveAppAuthenticationSecret(AppAuthentication::make()->generateSecret());

        expect(authActivity('mfa_enabled'))
            ->subject_id->toBe($admin->id)
            ->and(authActivity('mfa_enabled')->properties->get('provider'))->toBe('app');

        $admin->saveAppAuthenticationSecret(null);

        expect(authActivity('mfa_disabled')->properties->get('provider'))->toBe('app');

        $admin->toggleEmailAuthentication(true);

        expect(authActivity('mfa_enabled')->properties->get('provider'))->toBe('email');

        $admin->toggleEmailAuthentication(false);

        expect(authActivity('mfa_disabled')->properties->get('provider'))->toBe('email');
    });

    it('does not log a new authenticator secret replacing an old one as enabling again', function (): void {
        $admin = admin();
        $admin->saveAppAuthenticationSecret('FIRSTSECRETFIRSTSECRET');
        $count = Activity::query()->where('event', 'mfa_enabled')->count();

        $admin->saveAppAuthenticationSecret('SECONDSECRETSECONDSECRET');

        expect(Activity::query()->where('event', 'mfa_enabled')->count())->toBe($count);
    });
});

describe('user guards', function (): void {
    it('logs each authentication event for a user', function (string $event, Closure $raise, ?string $guard): void {
        $user = User::factory()->create();

        $raise($user);

        $activity = authActivity($event);

        expect($activity)->not->toBeNull()
            ->subject_type->toBe('user')
            ->subject_id->toBe($user->id)
            ->causer_id->toBe($user->id)
            ->and($activity->properties->get('guard'))->toBe($guard)
            ->and($activity->properties->has('ip'))->toBeTrue();
    })->with([
        'registered' => ['registered', fn (User $user) => event(new Registered($user)), 'web'],
        'login' => ['login', fn (User $user) => event(new LoginEvent('sanctum', $user, false)), 'sanctum'],
        'logout' => ['logout', fn (User $user) => event(new Logout('sanctum', $user)), 'sanctum'],
        'failed login' => ['failed_login', fn (User $user) => event(new Failed('sanctum', $user, ['email' => $user->email, 'password' => 'nope'])), 'sanctum'],
        'password reset' => ['password_reset', fn (User $user) => event(new PasswordReset($user)), 'web'],
        'email verified' => ['email_verified', fn (User $user) => event(new Verified($user)), 'web'],
    ]);

    it('logs a lockout raised by the API with its guard and address', function (): void {
        $request = Request::create('/api/v1/auth/login', 'POST', ['email' => 'someone@example.com', 'password' => 'secret-value']);
        $request->attributes->set('ghostdash.auth.guard', 'sanctum');

        event(new Lockout($request));

        $activity = authActivity('lockout');

        expect($activity->properties->get('guard'))->toBe('sanctum')
            ->and($activity->properties->get('email'))->toBe('someone@example.com')
            ->and((string) $activity->getRawOriginal('properties'))->not->toContain('secret-value');
    });
});
