<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Automation\ManageReportAutomations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RunReportAutomationController extends Controller
{
    public function __invoke(Request $request, Organisation $organisation, Brand $brand, int $ruleId,
        ManageReportAutomations $service, ApiResponse $response): JsonResponse
    {
        $data = $request->validate(['idempotency_key' => ['required', 'string', 'min:16', 'max:128']]);

        return $response->success($service->execute($request->user(), $organisation, $brand, $ruleId, $data['idempotency_key']));
    }
}
