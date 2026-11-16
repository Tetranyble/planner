<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\CreateOrganisationRequest;
use App\Http\Resources\Directory\OrganisationSummaryResource;
use App\Services\Onboarding\CreateOrganisationService;
use Illuminate\Http\JsonResponse;

final class CreateOrganisationController extends Controller
{
    public function __invoke(
        CreateOrganisationRequest $request,
        CreateOrganisationService $service,
        ApiResponse $response,
    ): JsonResponse {
        $attributes = $request->validated();
        $organisation = $service->execute(
            $request->user(),
            $attributes['name'],
            $attributes['timezone'] ?? 'Africa/Lagos',
        );

        return $response->created(OrganisationSummaryResource::make($organisation)->resolve($request));
    }
}
