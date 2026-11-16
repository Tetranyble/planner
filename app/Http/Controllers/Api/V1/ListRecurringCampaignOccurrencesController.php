<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Scheduling\ManageRecurringCampaigns;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListRecurringCampaignOccurrencesController extends Controller
{
    public function __invoke(Request $request, Organisation $organisation, Brand $brand, int $scheduleId, ManageRecurringCampaigns $service, ApiResponse $response): JsonResponse
    {
        return $response->success($service->projected($request->user(), $organisation, $brand, $scheduleId));
    }
}
