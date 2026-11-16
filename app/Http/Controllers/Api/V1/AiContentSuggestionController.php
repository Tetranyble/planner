<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Ai\ManageAiSuggestions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AiContentSuggestionController extends Controller
{
    public function index(Request $request, Organisation $organisation, Brand $brand, ManageAiSuggestions $service, ApiResponse $response): JsonResponse
    {
        return $response->success($service->listing($request->user(), $organisation, $brand));
    }

    public function store(Request $request, Organisation $organisation, Brand $brand, ManageAiSuggestions $service, ApiResponse $response): JsonResponse
    {
        $input = $request->validate([
            'topic' => ['required', 'string', 'min:5', 'max:500'],
            'platform' => ['required', 'in:facebook,instagram,linkedin,x,tiktok,youtube'],
            'campaign_name' => ['nullable', 'string', 'max:180'],
            'audience' => ['nullable', 'string', 'max:200'],
            'goal' => ['nullable', 'string', 'max:200'],
        ]);

        return $response->created($service->generate($request->user(), $organisation, $brand, $input));
    }
}
