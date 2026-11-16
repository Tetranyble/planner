<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Automation\ManageReportAutomations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ReportAutomationController extends Controller
{
    public function index(Request $request, Organisation $organisation, Brand $brand, ManageReportAutomations $service, ApiResponse $response): JsonResponse
    {
        return $response->success($service->index($request->user(), $organisation, $brand));
    }

    public function store(Request $request, Organisation $organisation, Brand $brand, ManageReportAutomations $service, ApiResponse $response): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'min:3', 'max:120'],
            'trigger_kind' => ['sometimes', 'in:MANUAL,DAILY_UTC'],
            'from' => ['required_unless:trigger_kind,DAILY_UTC', 'nullable', 'date_format:Y-m-d'],
            'to' => ['required_unless:trigger_kind,DAILY_UTC', 'nullable', 'date_format:Y-m-d'],
            'hour_utc' => ['required_if:trigger_kind,DAILY_UTC', 'nullable', 'integer', 'between:0,23'],
            'lookback_days' => ['required_if:trigger_kind,DAILY_UTC', 'nullable', 'integer', 'between:1,30'],
            'max_runs_per_day' => ['required', 'integer', 'min:1', 'max:20']]);

        return $response->success($service->create($request->user(), $organisation, $brand, $data));
    }
}
