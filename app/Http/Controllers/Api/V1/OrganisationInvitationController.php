<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Membership\InviteOrganisationMemberRequest;
use App\Models\Organisation;
use App\Services\Membership\ManageOrganisationMembers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OrganisationInvitationController extends Controller
{
    public function store(InviteOrganisationMemberRequest $request, Organisation $organisation, ManageOrganisationMembers $service, ApiResponse $response): JsonResponse
    {
        $data = $request->validated();
        $invitation = $service->invite($request->user(), $organisation, $data['email'], $data['role']);

        return $response->created(['uuid' => $invitation->uuid, 'email' => $invitation->email, 'expires_at' => $invitation->expires_at->toIso8601String()]);
    }

    public function destroy(Request $request, Organisation $organisation, string $uuid, ManageOrganisationMembers $service, ApiResponse $response): JsonResponse
    {
        $service->cancelInvitation($request->user(), $organisation, $uuid);

        return $response->success(['cancelled' => true]);
    }
}
