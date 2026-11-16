<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\BrandStrategy\CreateDraftApprovalPolicyRequest;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\BrandStrategy\ManageBrandStrategy;
use Illuminate\Http\JsonResponse;

final class BrandApprovalPolicyController extends Controller
{
    public function store(CreateDraftApprovalPolicyRequest $request, Organisation $organisation, Brand $brand, ManageBrandStrategy $service, ApiResponse $response): JsonResponse
    {
        $id = $service->addDraftApprovalPolicy($request->user(), $organisation, $brand, $request->validated());

        return $response->created(['uuid' => $id, 'state' => 'DRAFT']);
    }
}
