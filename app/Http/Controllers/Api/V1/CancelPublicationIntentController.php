<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Content\ManagePublicationIntents;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CancelPublicationIntentController extends Controller
{
    public function __invoke(Request $request, Organisation $organisation, Brand $brand, int $jobId, ManagePublicationIntents $service, ApiResponse $response): JsonResponse
    {
        return $response->success($service->cancel($request->user(), $organisation, $brand, $jobId));
    }
}
