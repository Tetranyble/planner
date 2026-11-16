<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\BrandStrategy\CreateDraftClaimRequest;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\BrandStrategy\ManageBrandStrategy;
use Illuminate\Http\JsonResponse;

final class BrandClaimController extends Controller
{
    public function store(CreateDraftClaimRequest $request, Organisation $organisation, Brand $brand, ManageBrandStrategy $service, ApiResponse $response): JsonResponse
    {
        $id = $service->addDraftClaim($request->user(), $organisation, $brand, $request->validated());

        return $response->created(['uuid' => $id, 'status' => 'DRAFT']);
    }
}
