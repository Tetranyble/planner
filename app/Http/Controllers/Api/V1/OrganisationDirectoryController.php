<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\Directory\OrganisationSummaryResource;
use App\Services\Directory\DirectoryPage;
use App\Services\Directory\ListOrganisationsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OrganisationDirectoryController extends Controller
{
    public function __invoke(Request $request, ListOrganisationsService $service, ApiResponse $response): JsonResponse
    {
        $perPage = $request->validate(['per_page' => ['sometimes', 'integer', 'min:1', 'max:100']])['per_page'] ?? 20;

        return $response->success(DirectoryPage::from(
            $service->execute($request->user(), (int) $perPage),
            OrganisationSummaryResource::class,
        ));
    }
}
