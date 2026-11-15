<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * The one JSON shape every API response has, success or error:
 *
 *     { "success": bool, "message": string, "data": mixed, "errors": object|null }
 *
 * Clients can therefore read `success` and `message` without first working
 * out what kind of response they are holding.
 */
final class ApiResponse
{
    /**
     * @param  array<string, mixed>  $meta  Added next to "data", for pagination.
     * @param  array<string, string>  $headers
     */
    public static function success(mixed $data = null, ?string $message = null, int $status = 200, array $meta = [], array $headers = []): JsonResponse
    {
        $payload = [
            'success' => true,
            'message' => $message ?? __('api.messages.ok'),
            'data' => $data,
            'errors' => null,
        ];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status, $headers);
    }

    /**
     * @param  array<string, list<string>>|null  $errors  Field name to messages, for validation failures.
     * @param  array<string, mixed>  $headers
     * @param  array<string, mixed>  $debug  Only ever sent when APP_DEBUG is on.
     */
    public static function error(string $message, int $status, ?array $errors = null, array $headers = [], array $debug = []): JsonResponse
    {
        $payload = [
            'success' => false,
            'message' => $message,
            'data' => null,
            'errors' => $errors,
        ];

        if ($debug !== [] && config('app.debug')) {
            $payload['debug'] = $debug;
        }

        return response()->json($payload, $status, $headers);
    }
}
