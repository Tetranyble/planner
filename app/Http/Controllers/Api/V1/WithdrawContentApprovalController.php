<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Content\ManageContentApprovals;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WithdrawContentApprovalController extends Controller
{
    public function __invoke(Request $request, Organisation $organisation, Brand $brand, string $itemUuid, int $requestId, ManageContentApprovals $service, ApiResponse $response): JsonResponse
    {
        $service->withdraw($request->user(), $organisation, $brand, $itemUuid, $requestId);

        return $response->success(['id' => $requestId, 'status' => 'WITHDRAWN']);
    }
}
