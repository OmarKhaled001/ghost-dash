<?php

declare(strict_types=1);

use App\Filament\Pages\Auth\Login;
use App\Support\SensitiveData;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Event;

use function Pest\Livewire\livewire;

it('masks by key name at any depth and leaves the rest alone', function (): void {
    $redacted = SensitiveData::redact([
        'email' => 'omar@example.com',
        'password' => 'secret',
        'nested' => ['api_token' => 'abc', 'list' => [['Recovery_Codes' => ['one', 'two'], 'name' => 'kept']]],
        'removed_secret' => null,
        'json' => '{"password":"still-visible"}',
    ]);

    expect($redacted)->toBe([
        'email' => 'omar@example.com',
        'password' => SensitiveData::MASK,
        'nested' => ['api_token' => SensitiveData::MASK, 'list' => [['Recovery_Codes' => SensitiveData::MASK, 'name' => 'kept']]],
        'removed_secret' => null,
        // Strings are only opened when asked to.
        'json' => '{"password":"still-visible"}',
    ]);
});

it('looks inside JSON strings when asked, and returns other strings untouched', function (): void {
    $redacted = SensitiveData::redact([
        'snapshot' => '{"data":{"password":"secret","email":"omar@example.com"}}',
        'note' => '{not json',
        'plain' => 'hello',
    ], insideJson: true);

    expect($redacted['snapshot'])->toBe('{"data":{"password":"'.SensitiveData::MASK.'","email":"omar@example.com"}}')
        ->and($redacted['note'])->toBe('{not json')
        ->and($redacted['plain'])->toBe('hello');
});

it('removes a password from what a panel form really sends and receives', function (): void {
    // Telescope's request watcher stores the input and the JSON response of
    // every request it records. This is that data for a real Livewire form.
    $captured = [];

    Event::listen(function (RequestHandled $event) use (&$captured): void {
        $captured[] = [
            'payload' => $event->request->all(),
            'response' => json_decode((string) $event->response->getContent(), true) ?? [],
        ];
    });

    livewire(Login::class)
        ->fillForm(['email' => 'omar@example.com', 'password' => 'Panel-Secret-Passw0rd'])
        ->call('authenticate');

    $raw = (string) json_encode($captured);
    $redacted = (string) json_encode(SensitiveData::redact($captured, insideJson: true));

    // The plain value is in there to begin with, or this would prove nothing.
    expect($raw)->toContain('Panel-Secret-Passw0rd')
        ->and($redacted)->not->toContain('Panel-Secret-Passw0rd')
        ->and($redacted)->toContain('omar@example.com');
});
