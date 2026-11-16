<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Content\CreateContentVersionRequest;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Content\ManageContentStudio;
use Illuminate\Http\JsonResponse;

final class ContentVersionController extends Controller
{
    public function store(CreateContentVersionRequest $request, Organisation $organisation, Brand $brand, string $itemUuid, ManageContentStudio $service, ApiResponse $response): JsonResponse
    {
        return $response->created($service->append($request->user(), $organisation, $brand, $itemUuid, $request->validated()));
    }
}
