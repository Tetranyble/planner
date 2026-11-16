<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Publishing\MediaTikTokCreatorConsent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class QueryTikTokCreatorOptionsController extends Controller
{
    public function __invoke(Request $request, Organisation $organisation, Brand $brand, int $runId,
        MediaTikTokCreatorConsent $service, ApiResponse $response): JsonResponse
    {
        return $response->success($service->options($request->user(), $organisation, $brand, $runId));
    }
}
