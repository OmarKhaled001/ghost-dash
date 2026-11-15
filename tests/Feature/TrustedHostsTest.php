<?php

declare(strict_types=1);

use function Pest\Laravel\get;

/*
| Absolute URLs are built from the Host header. Outside local, a request for
| a host the application does not know must not get as far as building one.
*/

beforeEach(function (): void {
    config(['app.url' => 'https://panel.example.com', 'trustedproxy.proxies' => '127.0.0.1']);
});

it('answers any host in local, where APP_URL is rarely the address in use', function (): void {
    app()->detectEnvironment(fn (): string => 'local');

    get('http://whatever.test/up')->assertOk();
});

describe('outside local', function (): void {
    beforeEach(function (): void {
        app()->detectEnvironment(fn (): string => 'production');
    });

    it('answers for the host in APP_URL and its subdomains', function (string $host): void {
        get("https://{$host}/up")->assertOk();
    })->with(['panel.example.com', 'eu.panel.example.com']);

    it('refuses a host it does not know', function (string $host): void {
        get("https://{$host}/up")->assertStatus(400);
    })->with(['evil.example', 'example.com', 'panel.example.com.evil.example']);

    it('refuses a forged X-Forwarded-Host, even from a trusted proxy', function (): void {
        get('https://panel.example.com/up', ['X-Forwarded-Host' => 'evil.example'])->assertStatus(400);
    });

    it('answers for the extra patterns in TRUSTED_HOSTS', function (): void {
        config(['ghostdash.trusted_hosts' => ['^api\.example\.org$', '^10\.']]);

        get('https://api.example.org/up')->assertOk();
        get('http://10.0.3.17/up')->assertOk();
        get('https://other.example.org/up')->assertStatus(400);
    });
});
