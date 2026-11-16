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

final class PreviewRecurringCampaignController extends Controller
{
    public function __invoke(Request $request, Organisation $organisation, Brand $brand, int $scheduleId, ManageRecurringCampaigns $service, ApiResponse $response): JsonResponse
    {
        $data = Validator::make($request->query(), [
            'from' => ['required', 'date', 'max:48'], 'to' => ['required', 'date', 'max:48'],
        ])->validate();

        return $response->success($service->preview($request->user(), $organisation, $brand, $scheduleId, $data['from'], $data['to']));
    }
}
