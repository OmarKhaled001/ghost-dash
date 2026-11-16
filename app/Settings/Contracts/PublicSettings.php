<?php

declare(strict_types=1);

namespace App\Settings\Contracts;

/**
 * Settings a website or app may read without signing in, through
 * GET /api/v1/settings. Only what toPublicArray() returns is exposed, so a
 * settings class decides field by field what leaves the server.
 */
interface PublicSettings
{
    /**
     * @return array<string, mixed>
     */
    public function toPublicArray(): array;
}
