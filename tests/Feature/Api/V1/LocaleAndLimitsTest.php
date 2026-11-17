<?php

declare(strict_types=1);

use App\Models\Activity;
use App\Models\User;
use App\Notifications\Api\ResetPasswordNotification;
use App\Notifications\Api\VerifyEmailNotification;
use App\Settings\GeneralSettings;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\postJson;
use function Pest\Laravel\withHeader;

describe('language', function (): void {
    it('answers in English by default', function (): void {
        $response = postJson('/api/v1/auth/login', [])->assertStatus(422);

        expect($response->json('message'))->toBe('The given data was invalid.')
            ->and($response->json('errors.email.0'))->toBe('The email field is required.')
            ->and($response->headers->get('Content-Language'))->toBe('en');
    });

    it('answers in Arabic when asked, validation messages included', function (string $header): void {
        $response = withHeader('Accept-Language', $header)->postJson('/api/v1/auth/login', [])->assertStatus(422);

        expect($response->json('message'))->toBe('البيانات المدخلة غير صالحة.')
            ->and($response->json('errors.email.0'))->toBe('البريد الإلكتروني مطلوب.')
            ->and($response->json('errors.password.0'))->toBe('كلمة المرور مطلوب.')
            ->and($response->headers->get('Content-Language'))->toBe('ar');
    })->with([
        'plain' => ['ar'],
        'regional' => ['ar-EG'],
        'weighted list' => ['fr-FR, ar;q=0.8, en;q=0.5'],
    ]);

    it('translates business messages and errors', function (): void {
        User::factory()->create(['email' => 'nour@example.com']);

        withHeader('Accept-Language', 'ar')
            ->postJson('/api/v1/auth/login', ['email' => 'nour@example.com', 'password' => 'password'])
            ->assertJsonPath('message', 'تم تسجيل الدخول.');

        withHeader('Accept-Language', 'ar')
            ->postJson('/api/v1/auth/login', ['email' => 'nour@example.com', 'password' => 'wrong'])
            ->assertJsonPath('errors.email.0', 'بيانات الدخول هذه لا تطابق سجلاتنا.');

        withHeader('Accept-Language', 'ar')->getJson('/api/v1/auth/me')->assertJsonPath('message', 'لم تسجّل الدخول، أو انتهت صلاحية جلستك.');
        withHeader('Accept-Language', 'ar')->getJson('/api/v1/missing')->assertJsonPath('message', 'المورد المطلوب غير موجود.');
    });

    it('translates the rate limit message, which is produced before the controller runs', function (): void {
        foreach (range(1, 5) as $attempt) {
            postJson('/api/v1/auth/login', ['email' => 'a@example.com', 'password' => 'x']);
        }

        withHeader('Accept-Language', 'ar')
            ->postJson('/api/v1/auth/login', ['email' => 'a@example.com', 'password' => 'x'])
            ->assertStatus(429)
            ->assertJsonPath('message', 'محاولات كثيرة جدًا. يرجى الانتظار قبل المحاولة مجددًا.');
    });

    it('falls back to the default language for one it does not offer', function (): void {
        $response = withHeader('Accept-Language', 'fr-FR, de;q=0.8')->postJson('/api/v1/auth/login', []);

        expect($response->headers->get('Content-Language'))->toBe('en');
    });

    it('uses the default language from General settings when no header is sent', function (): void {
        $settings = resolve(GeneralSettings::class);
        $settings->default_locale = 'ar';
        $settings->save();

        // The test client always sends "Accept-Language: en-us,en;q=0.5".
        // An empty value is how to express "no preference" here.
        $response = withHeader('Accept-Language', '')->postJson('/api/v1/auth/login', []);

        expect($response->headers->get('Content-Language'))->toBe('ar')
            ->and($response->json('message'))->toBe('البيانات المدخلة غير صالحة.');
    });

    it('lets the header win over the configured default', function (): void {
        $settings = resolve(GeneralSettings::class);
        $settings->default_locale = 'ar';
        $settings->save();

        withHeader('Accept-Language', 'en')
            ->postJson('/api/v1/auth/login', [])
            ->assertJsonPath('message', 'The given data was invalid.');
    });

    it('writes emails in the language of the request', function (): void {
        $user = User::factory()->unverified()->create();

        app()->setLocale('ar');

        $verify = (new VerifyEmailNotification)->toMail($user);
        $reset = new ResetPasswordNotification('token')->toMail($user);

        expect($verify->subject)->toBe('وثّق بريدك الإلكتروني')
            ->and($verify->actionText)->toBe('توثيق البريد الإلكتروني')
            ->and($verify->introLines[0])->toBe('يرجى النقر على الزر أدناه لتوثيق بريدك الإلكتروني.')
            ->and($reset->subject)->toBe('إعادة تعيين كلمة المرور')
            ->and($reset->actionText)->toBe('إعادة تعيين كلمة المرور');
    });
});

describe('rate limiters', function (): void {
    it('gives every auth endpoint a limiter of its own', function (): void {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RoutingRoute $route): bool => str_starts_with($route->uri(), 'api/v1/auth/'));

        expect($routes)->toHaveCount(11);

        $limiters = $routes->map(function (RoutingRoute $route): string {
            $throttle = collect($route->gatherMiddleware())->first(fn (string $middleware): bool => str_starts_with($middleware, 'throttle:'));

            expect($throttle)->not->toBeNull("{$route->uri()} has no rate limiter");

            return str_replace('throttle:', '', (string) $throttle);
        });

        // One limiter per route, all of them defined.
        expect($limiters->unique())->toHaveCount(11);

        $limiters->each(fn (string $name) => expect(RateLimiter::limiter($name))->not->toBeNull("limiter [{$name}] is not defined"));
    });

    it('does not let an exhausted login limiter block password recovery', function (): void {
        foreach (range(1, 6) as $attempt) {
            postJson('/api/v1/auth/login', ['email' => 'nour@example.com', 'password' => 'wrong']);
        }

        postJson('/api/v1/auth/forgot-password', ['email' => 'nour@example.com'])->assertOk();
    });

    it('counts spellings the database treats as one address against one limit', function (): void {
        User::factory()->create(['email' => 'victim@example.com']);

        // The column's collation ignores case and accents, so all of these
        // are attempts on the same account.
        foreach (['victim@example.com', 'Victim@example.com', 'víctim@example.com', 'vïctim@example.com', 'VICTIM@example.com'] as $spelling) {
            postJson('/api/v1/auth/login', ['email' => $spelling, 'password' => 'wrong'])->assertStatus(422);
        }

        postJson('/api/v1/auth/login', ['email' => 'vîctim@example.com', 'password' => 'wrong'])->assertStatus(429);
    });

    it('caps sign-in attempts per address whatever accounts they name', function (): void {
        foreach (range(1, 20) as $attempt) {
            postJson('/api/v1/auth/login', ['email' => "user{$attempt}@example.com", 'password' => 'Spring2026!'])->assertStatus(422);
        }

        postJson('/api/v1/auth/login', ['email' => 'user21@example.com', 'password' => 'Spring2026!'])->assertStatus(429);
    });

    it('caps reset requests per address as well', function (): void {
        foreach (range(1, 10) as $attempt) {
            postJson('/api/v1/auth/forgot-password', ['email' => "user{$attempt}@example.com"])->assertOk();
        }

        postJson('/api/v1/auth/forgot-password', ['email' => 'user11@example.com'])->assertStatus(429);
    });

    it('answers 422, not 500, when the email is not a string', function (): void {
        postJson('/api/v1/auth/login', ['email' => ['a@example.com'], 'password' => 'x'])->assertStatus(422);
        postJson('/api/v1/auth/forgot-password', ['email' => ['a@example.com']])->assertStatus(422);
    });

    it('logs a lockout once, not once per refused request', function (): void {
        foreach (range(1, 9) as $attempt) {
            postJson('/api/v1/auth/login', ['email' => 'nour@example.com', 'password' => 'wrong']);
        }

        expect(Activity::query()->where('event', 'lockout')->count())->toBe(1);
    });

    it('protects every authenticated endpoint with auth:sanctum', function (): void {
        $authenticated = ['api/v1/auth/me', 'api/v1/auth/password', 'api/v1/auth/email/verification-notification', 'api/v1/auth/logout', 'api/v1/auth/logout-all'];

        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RoutingRoute $route): bool => in_array($route->uri(), $authenticated, true));

        expect($routes)->toHaveCount(5);

        foreach ($routes as $route) {
            expect($route->gatherMiddleware())->toContain('auth:sanctum');
        }
    });
});
