<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Content\InspectHeldPublicationPlans;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class InspectHeldPublicationPlansController extends Controller
{
    public function __invoke(Request $request, Organisation $organisation, Brand $brand,
        InspectHeldPublicationPlans $service, ApiResponse $response): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:50'],
            'ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ]);

        return $response->success($service->inspect($request->user(), $organisation, $brand, $validated['ids']));
    }
}
