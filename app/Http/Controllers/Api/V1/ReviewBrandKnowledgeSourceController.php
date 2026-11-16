<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\BrandStrategy\ReviewSourceRequest;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\BrandStrategy\GovernBrandClaims;
use Illuminate\Http\JsonResponse;

final class ReviewBrandKnowledgeSourceController extends Controller
{
    public function __invoke(ReviewSourceRequest $request, Organisation $organisation, Brand $brand, string $sourceUuid, GovernBrandClaims $service, ApiResponse $response): JsonResponse
    {
        $data = $request->validated();
        $service->reviewSource($request->user(), $organisation, $brand, $sourceUuid, $data['decision'], $data['rationale']);

        return $response->success(['trust_status' => $data['decision'] === 'VERIFY' ? 'TRUSTED' : 'REJECTED']);
    }
}
