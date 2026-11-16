<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Content\PublicationCalendar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PlannerIntegrationCalendarController extends Controller
{
    public function __invoke(Request $request, Organisation $organisation, Brand $brand,
        PublicationCalendar $calendar, ApiResponse $response): JsonResponse
    {
        $input = $request->validate(['from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d']]);

        return $response->success($calendar->list($request->user(), $organisation, $brand,
            $input['from'], $input['to']));
    }
}
