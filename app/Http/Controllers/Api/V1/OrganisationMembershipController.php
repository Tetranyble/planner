<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Membership\ChangeOrganisationMemberRoleRequest;
use App\Models\Organisation;
use App\Services\Membership\ManageOrganisationMembers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OrganisationMembershipController extends Controller
{
    public function index(Request $request, Organisation $organisation, ManageOrganisationMembers $service, ApiResponse $response): JsonResponse
    {
        $perPage = $request->validate(['per_page' => ['sometimes', 'integer', 'min:1', 'max:100']])['per_page'] ?? 25;

        return $response->success($service->directory($request->user(), $organisation, (int) $perPage));
    }

    public function update(ChangeOrganisationMemberRoleRequest $request, Organisation $organisation, int $userId, ManageOrganisationMembers $service, ApiResponse $response): JsonResponse
    {
        $service->changeRole($request->user(), $organisation, $userId, $request->validated('role'));

        return $response->success(['updated' => true]);
    }

    public function destroy(Request $request, Organisation $organisation, int $userId, ManageOrganisationMembers $service, ApiResponse $response): JsonResponse
    {
        $service->revoke($request->user(), $organisation, $userId);

        return $response->success(['revoked' => true]);
    }
}
