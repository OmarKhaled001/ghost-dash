<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What an API token may be used for. A token issued without an explicit
 * list receives all of them.
 */
enum TokenAbility: string
{
    case AccountRead = 'account:read';
    case AccountWrite = 'account:write';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
