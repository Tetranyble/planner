<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\BrandStrategy\CreateClaimEvidenceRequest;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\BrandStrategy\GovernBrandClaims;
use Illuminate\Http\JsonResponse;

final class BrandClaimEvidenceController extends Controller
{
    public function store(CreateClaimEvidenceRequest $request, Organisation $organisation, Brand $brand, string $claimUuid, GovernBrandClaims $service, ApiResponse $response): JsonResponse
    {
        $id = $service->addEvidence($request->user(), $organisation, $brand, $claimUuid, $request->validated());

        return $response->created(['uuid' => $id, 'review_state' => 'PENDING']);
    }
}
