<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Content\LinkStorageMediaRequest;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Content\ManageContentStudio;
use Illuminate\Http\JsonResponse;

final class MediaAssetController extends Controller
{
    public function store(LinkStorageMediaRequest $request, Organisation $organisation, Brand $brand, ManageContentStudio $service, ApiResponse $response): JsonResponse
    {
        return $response->created($service->linkMedia($request->user(), $organisation, $brand, (int) $request->validated('storage_media_id')));
    }
}
