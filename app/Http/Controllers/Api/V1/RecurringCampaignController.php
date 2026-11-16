<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Scheduling\ManageRecurringCampaigns;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class RecurringCampaignController extends Controller
{
    public function index(Request $request, Organisation $organisation, Brand $brand, ManageRecurringCampaigns $service, ApiResponse $response): JsonResponse
    {
        return $response->success($service->listing($request->user(), $organisation, $brand));
    }

    public function store(Request $request, Organisation $organisation, Brand $brand, ManageRecurringCampaigns $service, ApiResponse $response): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'target_uuid' => ['required', 'uuid'],
            'rrule' => ['required', 'string', 'max:160'],
            'timezone' => ['required', 'string', 'max:64'],
            'start_local' => ['required', 'date_format:Y-m-d H:i:s'],
            'end_at' => ['nullable', 'string', 'max:48'],
            'max_occurrences' => ['required', 'integer', 'between:1,180'],
        ])->validate();

        return $response->created($service->create($request->user(), $organisation, $brand, $data));
    }
}
