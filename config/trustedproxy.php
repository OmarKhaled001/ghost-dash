<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted proxies
    |--------------------------------------------------------------------------
    |
    | Behind a load balancer or reverse proxy every request appears to come
    | from the proxy. The API rate limiters, the recorded sign-in address and
    | HTTPS detection (HSTS, secure cookies) would all be looking at the wrong
    | machine. List the proxy addresses or ranges here, comma-separated, for
    | example "10.0.0.0/8".
    |
    | "*" trusts every address named in X-Forwarded-For, the ones a client
    | wrote into it included. Most proxies append to that header instead of
    | replacing it, and a client could then choose the address it is rate
    | limited by. Use "*" only when your proxy overwrites the header.
    |
    | Empty means no proxy is trusted and X-Forwarded-* headers are ignored,
    | which is the right setting for a server that faces the internet itself.
    |
    */

    'proxies' => env('TRUSTED_PROXIES') ?: null,

];
