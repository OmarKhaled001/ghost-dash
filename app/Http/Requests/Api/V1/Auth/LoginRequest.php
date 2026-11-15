<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use App\Enums\TokenAbility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class LoginRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'abilities' => ['nullable', 'array'],
            'abilities.*' => ['string', 'distinct', Rule::enum(TokenAbility::class)],
        ];
    }

    public function deviceName(): string
    {
        return $this->string('device_name', 'api')->toString() ?: 'api';
    }

    /**
     * @return list<string>|null
     */
    public function abilities(): ?array
    {
        $abilities = $this->validated('abilities');

        return is_array($abilities) && $abilities !== []
            ? array_values(array_filter($abilities, is_string(...)))
            : null;
    }
}
