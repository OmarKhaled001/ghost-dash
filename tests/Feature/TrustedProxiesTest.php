<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::get('/_test/ip', fn (Request $request): array => [
        'ip' => $request->ip(),
        'secure' => $request->isSecure(),
    ]);
});

it('ignores forwarded headers when no proxy is trusted', function (): void {
    config(['trustedproxy.proxies' => null]);

    $this->get('/_test/ip', ['X-Forwarded-For' => '203.0.113.7', 'X-Forwarded-Proto' => 'https'])
        ->assertExactJson(['ip' => '127.0.0.1', 'secure' => false]);
});

it('reads the client address and scheme from a trusted proxy', function (): void {
    config(['trustedproxy.proxies' => '127.0.0.1']);

    $this->get('/_test/ip', ['X-Forwarded-For' => '203.0.113.7', 'X-Forwarded-Proto' => 'https'])
        ->assertExactJson(['ip' => '203.0.113.7', 'secure' => true]);
});

it('does not believe a proxy that is not on the list', function (): void {
    config(['trustedproxy.proxies' => '10.0.0.1, 10.0.0.2']);

    $this->get('/_test/ip', ['X-Forwarded-For' => '203.0.113.7'])
        ->assertJsonPath('ip', '127.0.0.1');
});

it('takes the address the proxy saw, not one the client wrote into the header', function (): void {
    config(['trustedproxy.proxies' => '127.0.0.0/8']);

    // A proxy that appends: the client sent the first value, the proxy
    // added the address it really talked to.
    $this->get('/_test/ip', ['X-Forwarded-For' => '198.51.100.99, 203.0.113.7'])
        ->assertJsonPath('ip', '203.0.113.7');
});

it('believes the client-written address under "*", which is why the README steers away from it', function (): void {
    config(['trustedproxy.proxies' => '*']);

    $this->get('/_test/ip', ['X-Forwarded-For' => '198.51.100.99, 203.0.113.7'])
        ->assertJsonPath('ip', '198.51.100.99');
});
