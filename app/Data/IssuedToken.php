<?php

declare(strict_types=1);

namespace App\Data;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;
use SensitiveParameter;

/**
 * A freshly created API token. The plain-text value exists only here: the
 * database keeps a hash, so it can be shown to the client exactly once.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class IssuedToken implements Arrayable
{
    /**
     * @param  list<string>  $abilities
     */
    public function __construct(
        #[SensitiveParameter] public string $plainTextToken,
        public array $abilities,
        public ?CarbonImmutable $expiresAt,
    ) {}

    /**
     * @return array{token: string, token_type: string, abilities: list<string>, expires_at: string|null}
     */
    public function toArray(): array
    {
        return [
            'token' => $this->plainTextToken,
            'token_type' => 'Bearer',
            'abilities' => $this->abilities,
            'expires_at' => $this->expiresAt?->toIso8601String(),
        ];
    }
}
