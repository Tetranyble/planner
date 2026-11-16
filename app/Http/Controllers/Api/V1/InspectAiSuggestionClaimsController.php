<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Ai\ReviewAiSuggestionClaims;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class InspectAiSuggestionClaimsController extends Controller
{
    public function __invoke(Request $request, Organisation $organisation, Brand $brand, string $suggestionUuid,
        ReviewAiSuggestionClaims $service, ApiResponse $response): JsonResponse
    {
        return $response->success($service->inspect($request->user(), $organisation, $brand, $suggestionUuid));
    }
}
