<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use App\Http\Requests\Api\V1\AuthenticatedRequest;
use Illuminate\Validation\Rules\Password;
use Override;

final class UpdatePasswordRequest extends AuthenticatedRequest
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function rules(): array
    {
        return [
            // A stolen token alone must not be enough to take over the
            // account by changing its password.
            'current_password' => ['required', 'string', 'current_password:sanctum'],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', Password::defaults()],
        ];
    }
}
