<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Membership\AcceptOrganisationInvitationRequest;
use App\Http\Resources\Directory\OrganisationSummaryResource;
use App\Services\Membership\ManageOrganisationMembers;
use Illuminate\Http\JsonResponse;

final class AcceptOrganisationInvitationController extends Controller
{
    public function __invoke(AcceptOrganisationInvitationRequest $request, ManageOrganisationMembers $service, ApiResponse $response): JsonResponse
    {
        $organisation = $service->accept($request->user(), $request->validated('token'));

        return $response->success(OrganisationSummaryResource::make($organisation)->resolve($request));
    }
}
