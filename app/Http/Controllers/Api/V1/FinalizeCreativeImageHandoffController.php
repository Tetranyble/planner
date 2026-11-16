<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Creative\FinalizeCreativeImageHandoff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** No MCP or delegated-agent endpoint: first-party operator alone can finalize. */
final class FinalizeCreativeImageHandoffController extends Controller
{
    public function __invoke(Request $request, Organisation $organisation, Brand $brand,
        string $handoffUuid, FinalizeCreativeImageHandoff $service, ApiResponse $response): JsonResponse
    {
        $data = $request->validate([
            'storage_media_id' => ['required', 'integer', 'min:1'],
            'reviewed' => ['required', 'accepted'],
        ]);

        return $response->success($service->link($request->user(), $organisation, $brand,
            $handoffUuid, (int) $data['storage_media_id'], (bool) $data['reviewed']));
    }
}
