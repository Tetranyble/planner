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

final class ChangeRecurringCampaignStateController extends Controller
{
    public function __invoke(Request $request, Organisation $organisation, Brand $brand, int $scheduleId, ManageRecurringCampaigns $service, ApiResponse $response): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'state' => ['required', 'in:ACTIVE,PAUSED,RETIRED'],
            'expected_version' => ['required', 'integer', 'min:0'],
        ])->validate();

        return $response->success($service->transition($request->user(), $organisation, $brand, $scheduleId,
            $data['state'], (int) $data['expected_version']));
    }
}
