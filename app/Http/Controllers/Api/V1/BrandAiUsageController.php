<?php

namespace App\Http\Controllers\Api\V1;

use App\Authorization\BrandPermissionGuard;
use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Ai\BrandAiUsageBudget;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BrandAiUsageController extends Controller
{
    public function __invoke(Request $request, Organisation $organisation, Brand $brand, BrandAiUsageBudget $budget,
        BrandPermissionGuard $guard, ApiResponse $response): JsonResponse
    {
        if ((int) $brand->organisation_id !== (int) $organisation->id || ! $guard->allows($request->user(), $brand, 'content_item.index')) {
            throw new AuthorizationException('AI usage access denied.');
        }

        return $response->success($budget->usage($organisation, $brand));
    }
}
