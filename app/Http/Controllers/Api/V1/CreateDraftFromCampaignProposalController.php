<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Ai\ManageCampaignProposals;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CreateDraftFromCampaignProposalController extends Controller
{
    public function __invoke(Request $request, Organisation $organisation, Brand $brand, string $proposalUuid,
        ManageCampaignProposals $service, ApiResponse $response): JsonResponse
    {
        $input = $request->validate(['title' => ['required', 'string', 'min:2', 'max:180'],
            'copy_text' => ['required', 'string', 'min:20', 'max:10000']]);

        return $response->created($service->createEditorialDraft($request->user(), $organisation, $brand, $proposalUuid, $input));
    }
}
