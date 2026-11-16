<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Ai\ManageCampaignProposals;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AiCampaignProposalController extends Controller
{
    public function index(Request $request, Organisation $organisation, Brand $brand, ManageCampaignProposals $service, ApiResponse $response): JsonResponse
    {
        return $response->success($service->listing($request->user(), $organisation, $brand));
    }

    public function store(Request $request, Organisation $organisation, Brand $brand, ManageCampaignProposals $service, ApiResponse $response): JsonResponse
    {
        $input = $request->validate([
            'title' => ['required', 'string', 'min:5', 'max:180'],
            'objective' => ['required', 'string', 'min:5', 'max:180'],
            'audience' => ['required', 'string', 'min:3', 'max:200'],
            'platforms' => ['required', 'array', 'min:1', 'max:6'],
            'platforms.*' => ['required', 'distinct', 'in:facebook,instagram,linkedin,x,tiktok,youtube'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:30'],
            'use_provider_evidence' => ['sometimes', 'boolean'],
        ]);

        return $response->created($service->propose($request->user(), $organisation, $brand, $input));
    }
}
