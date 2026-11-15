<?php

declare(strict_types=1);

/*
| Signs in to a running GhostDash panel over HTTP, the way a browser does,
| and fails loudly if that does not work. Used by the install-e2e workflow
| to prove that a fresh installation ends in a panel someone can enter.
|
|   php assert-admin-sign-in.php <base-url> <panel-path> <email>
|
| The password is read from GHOSTDASH_ADMIN_PASSWORD so it never appears in
| a process list or a workflow log.
*/

[$baseUrl, $panelPath, $email] = array_slice($argv, 1) + [null, null, null];
$password = getenv('GHOSTDASH_ADMIN_PASSWORD');

if (! $baseUrl || ! $panelPath || ! $email || ! is_string($password) || $password === '') {
    fwrite(STDERR, "Usage: GHOSTDASH_ADMIN_PASSWORD=... php assert-admin-sign-in.php <base-url> <panel-path> <email>\n");
    exit(2);
}

$baseUrl = rtrim($baseUrl, '/');
$panel = $baseUrl.'/'.trim($panelPath, '/');

$fail = function (string $message): never {
    fwrite(STDERR, "FAIL  {$message}\n");
    exit(1);
};

$pass = function (string $message): void {
    echo "ok    {$message}\n";
};

// One curl handle, so cookies set by one response are sent with the next.
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEFILE => '',
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_TIMEOUT => 30,
]);

/**
 * @param  array<int, string>  $headers
 * @return array{0: int, 1: string, 2: string}
 */
$request = function (string $url, ?string $body = null, array $headers = []) use ($curl): array {
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_HTTPGET => $body === null,
        CURLOPT_POST => $body !== null,
        CURLOPT_HTTPHEADER => $headers,
    ]);

    if ($body !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
    }

    $response = curl_exec($curl);

    if ($response === false) {
        fwrite(STDERR, 'FAIL  '.$url.': '.curl_error($curl)."\n");
        exit(1);
    }

    return [
        (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE),
        (string) $response,
        (string) curl_getinfo($curl, CURLINFO_REDIRECT_URL),
    ];
};

// 1. A guest is sent to the login page.
[$status, , $location] = $request($panel);
$status === 302 && str_ends_with($location, '/login')
    ? $pass("guest is redirected from {$panel} to the login page")
    : $fail("expected a redirect to the login page from {$panel}, got HTTP {$status} {$location}");

// 2. The login page renders.
[$status, $html] = $request($panel.'/login');
$status === 200 ? $pass('login page answers 200') : $fail("login page answered HTTP {$status}");

// 3. Submit the form as Livewire would: the component snapshot from the
//    page, the two field values, and a call to authenticate().
if (preg_match('/wire:snapshot="([^"]+)"[^>]*wire:name="[^"]*Auth\\\\Login"/', $html, $match) !== 1) {
    $fail('the login component was not found on the page');
}

$snapshot = html_entity_decode($match[1], ENT_QUOTES);

if (preg_match('/data-csrf="([^"]+)"/', $html, $csrf) !== 1 || preg_match('/data-update-uri="([^"]+)"/', $html, $uri) !== 1) {
    $fail('the Livewire CSRF token or update URL was not found on the page');
}

$updateUrl = str_starts_with($uri[1], 'http') ? $uri[1] : $baseUrl.$uri[1];

$payload = json_encode([
    '_token' => $csrf[1],
    'components' => [[
        'snapshot' => $snapshot,
        'updates' => ['data.email' => $email, 'data.password' => $password],
        'calls' => [['path' => '', 'method' => 'authenticate', 'params' => []]],
    ]],
], JSON_THROW_ON_ERROR);

[$status, $body] = $request($updateUrl, $payload, [
    'Content-Type: application/json',
    'Accept: application/json',
    'X-Livewire: 1',
    'X-CSRF-TOKEN: '.$csrf[1],
    'Referer: '.$panel.'/login',
]);

if ($status !== 200) {
    $fail("signing in answered HTTP {$status}: ".substr(strip_tags($body), 0, 300));
}

$effects = json_decode($body, true)['components'][0]['effects'] ?? [];

// The response echoes the form state, password included, so it is never
// printed: a failed run must not put the password in a CI log.
isset($effects['redirect'])
    ? $pass("credentials for {$email} were accepted")
    : $fail("the credentials for {$email} were not accepted (wrong password, inactive admin, or an admin without a role)");

// 4. The session that came back opens the panel.
[$status, $html] = $request($panel);
$status === 200
    ? $pass("{$email} can open the panel")
    : $fail("the panel answered HTTP {$status} after signing in");

curl_close($curl);

echo "\nThe super admin can sign in.\n";
