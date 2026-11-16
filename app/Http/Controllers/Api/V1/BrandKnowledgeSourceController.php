<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\BrandStrategy\CreateKnowledgeSourceRequest;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\BrandStrategy\GovernBrandClaims;
use Illuminate\Http\JsonResponse;

final class BrandKnowledgeSourceController extends Controller
{
    public function store(CreateKnowledgeSourceRequest $request, Organisation $organisation, Brand $brand, GovernBrandClaims $service, ApiResponse $response): JsonResponse
    {
        $id = $service->addSource($request->user(), $organisation, $brand, $request->validated());

        return $response->created(['uuid' => $id, 'trust_status' => 'PENDING']);
    }
}
