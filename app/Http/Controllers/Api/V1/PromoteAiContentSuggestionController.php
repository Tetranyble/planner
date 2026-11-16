<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Ai\ManageAiSuggestions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PromoteAiContentSuggestionController extends Controller
{
    public function __invoke(Request $request, Organisation $organisation, Brand $brand, string $suggestionUuid,
        ManageAiSuggestions $service, ApiResponse $response): JsonResponse
    {
        $input = $request->validate(['title' => ['required', 'string', 'max:180']]);

        return $response->created($service->promote($request->user(), $organisation, $brand, $suggestionUuid, $input));
    }
}
