<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\Directory\BrandSummaryResource;
use App\Models\Organisation;
use App\Services\Directory\DirectoryPage;
use App\Services\Directory\ListBrandsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BrandDirectoryController extends Controller
{
    public function __invoke(Request $request, Organisation $organisation, ListBrandsService $service, ApiResponse $response): JsonResponse
    {
        $perPage = $request->validate(['per_page' => ['sometimes', 'integer', 'min:1', 'max:100']])['per_page'] ?? 20;

        return $response->success(DirectoryPage::from(
            $service->execute($request->user(), $organisation, (int) $perPage),
            BrandSummaryResource::class,
        ));
    }
}
