<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Content\SubmitContentApprovalRequest;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Content\ManageContentApprovals;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ContentApprovalRequestController extends Controller
{
    public function index(Request $request, Organisation $organisation, Brand $brand, string $itemUuid, ManageContentApprovals $service, ApiResponse $response): JsonResponse
    {
        return $response->success($service->listing($request->user(), $organisation, $brand, $itemUuid));
    }

    public function store(SubmitContentApprovalRequest $request, Organisation $organisation, Brand $brand, string $itemUuid, string $targetUuid, ManageContentApprovals $service, ApiResponse $response): JsonResponse
    {
        return $response->created($service->submit($request->user(), $organisation, $brand, $itemUuid, $targetUuid, $request->validated('expected_target_hash')));
    }
}
