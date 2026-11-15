<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Masks values by the name of their key, at any depth. One list
 * (ghostdash.activity_log.redact) decides what counts as sensitive, for the
 * activity log and for Telescope alike.
 */
final class SensitiveData
{
    public const string MASK = '[redacted]';

    /**
     * @param  array<array-key, mixed>  $data
     * @param  bool  $insideJson  Also look inside string values that hold
     *                            JSON, which is how Livewire ships a
     *                            component's state, form fields included.
     * @return array<array-key, mixed>
     */
    public static function redact(array $data, bool $insideJson = false): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && self::isSensitive($key)) {
                // Null stays null, so "the secret was removed" is still
                // distinguishable from "the secret was changed".
                $data[$key] = $value === null ? null : self::MASK;

                continue;
            }

            if (is_array($value)) {
                $data[$key] = self::redact($value, $insideJson);

                continue;
            }

            if ($insideJson && is_string($value)) {
                $data[$key] = self::redactJson($value);
            }
        }

        return $data;
    }

    public static function isSensitive(string $key): bool
    {
        $key = mb_strtolower($key);

        return array_any(
            config('ghostdash.activity_log.redact', []),
            fn (string $needle): bool => str_contains($key, $needle),
        );
    }

    private static function redactJson(string $value): string
    {
        if (! str_starts_with($value, '{') && ! str_starts_with($value, '[')) {
            return $value;
        }

        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            return $value;
        }

        return (string) json_encode(self::redact($decoded, insideJson: true));
    }
}
