<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SeoRequest;
use App\Services\SeoService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class SeoController extends Controller
{
    /**
     * The SEO for one page of a separate frontend: GET /api/v1/seo?path=/about.
     */
    public function show(SeoRequest $request, SeoService $seo): JsonResponse
    {
        abort_unless(SeoService::enabled(), 404);

        return ApiResponse::success($seo->resolve($request->pagePath())->toArray());
    }
}
