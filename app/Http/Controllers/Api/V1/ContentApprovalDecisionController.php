<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Content\DecideContentApprovalRequest;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Content\ManageContentApprovals;
use Illuminate\Http\JsonResponse;

final class ContentApprovalDecisionController extends Controller
{
    public function store(DecideContentApprovalRequest $request, Organisation $organisation, Brand $brand, string $itemUuid, int $requestId, ManageContentApprovals $service, ApiResponse $response): JsonResponse
    {
        $data = $request->validated();

        return $response->success($service->decide($request->user(), $organisation, $brand, $itemUuid, $requestId, $data['decision'], $data['comment']));
    }
}
