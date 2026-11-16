<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Content\CreateContentItemRequest;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Content\ManageContentStudio;
use Illuminate\Http\JsonResponse;

final class ContentItemController extends Controller
{
    public function store(CreateContentItemRequest $request, Organisation $organisation, Brand $brand, ManageContentStudio $service, ApiResponse $response): JsonResponse
    {
        return $response->created($service->create($request->user(), $organisation, $brand, $request->validated()));
    }
}
