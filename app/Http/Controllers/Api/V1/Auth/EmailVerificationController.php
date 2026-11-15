<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AuthenticatedRequest;
use App\Services\UserAuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class EmailVerificationController extends Controller
{
    /**
     * Reached from the link in the verification email. There is no token:
     * the signed URL is the proof.
     */
    public function verify(int $id, string $hash, UserAuthService $auth): JsonResponse
    {
        $verifiedNow = $auth->verifyEmail($id, $hash);

        return ApiResponse::success(message: $verifiedNow
            ? __('api.messages.email_verified')
            : __('api.messages.email_already_verified'));
    }

    public function resend(AuthenticatedRequest $request, UserAuthService $auth): JsonResponse
    {
        $sent = $auth->resendVerification($request->authenticatedUser());

        return ApiResponse::success(message: $sent
            ? __('api.messages.verification_link_sent')
            : __('api.messages.email_already_verified'));
    }
}
