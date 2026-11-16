<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Automation\ManageReportAutomations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class UpdateReportAutomationStatusController extends Controller
{
    public function __invoke(Request $request, Organisation $organisation, Brand $brand, int $ruleId,
        ManageReportAutomations $service, ApiResponse $response): JsonResponse
    {
        $data = $request->validate(['status' => ['required', 'in:ENABLED,PAUSED,RETIRED']]);

        return $response->success($service->status($request->user(), $organisation, $brand, $ruleId, $data['status']));
    }
}
