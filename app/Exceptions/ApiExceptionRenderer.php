<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Turns any exception thrown under /api into the API envelope.
 *
 * Laravel has already normalised the exception by the time it gets here:
 * a missing model is a 404 HttpException, a failed policy or a missing
 * token ability is a 403, and so on. What is left to decide is the wording.
 */
final class ApiExceptionRenderer
{
    public function render(Throwable $exception): JsonResponse
    {
        if ($exception instanceof ValidationException) {
            return ApiResponse::error(__('api.errors.validation'), $exception->status, $exception->errors());
        }

        if ($exception instanceof AuthenticationException) {
            return ApiResponse::error(__('api.errors.unauthenticated'), 401);
        }

        if ($exception instanceof InvalidSignatureException) {
            return ApiResponse::error(__('api.errors.invalid_signature'), 403);
        }

        if ($exception instanceof HttpExceptionInterface) {
            return $this->http($exception);
        }

        // Anything else is a bug or an outage. What went wrong is for the
        // logs, not for whoever happens to be calling the API.
        return ApiResponse::error(
            config('app.debug') ? $exception->getMessage() : __('api.errors.server'),
            500,
            debug: [
                'exception' => $exception::class,
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ],
        );
    }

    private function http(HttpExceptionInterface $exception): JsonResponse
    {
        $status = $exception->getStatusCode();

        $message = match ($status) {
            401 => __('api.errors.unauthenticated'),
            403 => __('api.errors.forbidden'),
            404 => __('api.errors.not_found'),
            405 => __('api.errors.method_not_allowed'),
            419 => __('api.errors.session_expired'),
            429 => __('api.errors.too_many_requests'),
            503 => __('api.errors.unavailable'),
            default => $status >= 500 ? __('api.errors.server') : __('api.errors.bad_request'),
        };

        // Retry-After on a 429 and Allow on a 405 are part of the answer.
        return ApiResponse::error($message, $status, headers: $exception->getHeaders());
    }
}
