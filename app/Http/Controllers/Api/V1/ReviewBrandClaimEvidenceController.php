<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\BrandStrategy\ReviewEvidenceRequest;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\BrandStrategy\GovernBrandClaims;
use Illuminate\Http\JsonResponse;

final class ReviewBrandClaimEvidenceController extends Controller
{
    public function __invoke(ReviewEvidenceRequest $request, Organisation $organisation, Brand $brand, string $evidenceUuid, GovernBrandClaims $service, ApiResponse $response): JsonResponse
    {
        $data = $request->validated();
        $service->reviewEvidence($request->user(), $organisation, $brand, $evidenceUuid, $data['decision'], $data['rationale']);

        return $response->success(['review_state' => $data['decision'] === 'VERIFY' ? 'VERIFIED' : 'REJECTED']);
    }
}
