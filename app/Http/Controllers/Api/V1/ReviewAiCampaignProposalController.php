<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Ai\ManageCampaignProposals;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ReviewAiCampaignProposalController extends Controller
{
    public function __invoke(Request $request, Organisation $organisation, Brand $brand, string $proposalUuid,
        ManageCampaignProposals $service, ApiResponse $response): JsonResponse
    {
        $input = $request->validate([
            'decision' => ['required', 'in:APPROVED,REJECTED'],
            'note' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        return $response->success($service->review($request->user(), $organisation, $brand, $proposalUuid, $input));
    }
}
