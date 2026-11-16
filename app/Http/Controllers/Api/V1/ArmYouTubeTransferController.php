<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Publishing\ManageYouTubeTransfers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ArmYouTubeTransferController extends Controller
{
    public function __invoke(Request $request, Organisation $organisation, Brand $brand, int $transferId,
        ManageYouTubeTransfers $service, ApiResponse $response): JsonResponse
    {
        $data = $request->validate(['version' => ['required', 'integer', 'min:1']]);

        return $response->success($service->arm($request->user(), $organisation, $brand, $transferId, (int) $data['version']));
    }
}
