<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Content\ListStorageMediaCandidates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StorageMediaCandidateController extends Controller
{
    public function index(Request $request, Organisation $organisation, Brand $brand,
        ListStorageMediaCandidates $service, ApiResponse $response): JsonResponse
    {
        $data = $request->validate(['before_id' => ['sometimes', 'integer', 'min:1']]);

        return $response->success($service->list($request->user(), $organisation, $brand, (int) ($data['before_id'] ?? 0)));
    }
}
