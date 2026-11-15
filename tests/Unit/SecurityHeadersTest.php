<?php

declare(strict_types=1);

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

pest()->extend(TestCase::class);

it('adds the baseline security headers to a response', function (): void {
    $response = (new SecurityHeaders)->handle(Request::create('/'), fn (): Response => new Response('ok'));

    expect($response->headers->get('X-Frame-Options'))->toBe('SAMEORIGIN')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin')
        ->and($response->headers->get('Permissions-Policy'))->toContain('camera=()')
        ->and($response->headers->has('Strict-Transport-Security'))->toBeFalse();
});

it('sends HSTS only for secure requests in production', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    $middleware = new SecurityHeaders;
    $secure = $middleware->handle(Request::create('https://example.com/'), fn (): Response => new Response('ok'));
    $plain = $middleware->handle(Request::create('http://example.com/'), fn (): Response => new Response('ok'));

    expect($secure->headers->get('Strict-Transport-Security'))->toBe('max-age=31536000; includeSubDomains')
        ->and($plain->headers->has('Strict-Transport-Security'))->toBeFalse();
});

it('keeps a header the application set on purpose', function (): void {
    $response = (new SecurityHeaders)->handle(
        Request::create('/'),
        fn (): Response => new Response('ok', 200, ['X-Frame-Options' => 'DENY']),
    );

    expect($response->headers->get('X-Frame-Options'))->toBe('DENY');
});
