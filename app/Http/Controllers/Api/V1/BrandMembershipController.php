<?php

namespace App\Http\Controllers\Api\V1;

use App\Common\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Membership\AddBrandMemberRequest;
use App\Http\Requests\Membership\ChangeBrandMemberRoleRequest;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Membership\ManageBrandMembers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BrandMembershipController extends Controller
{
    public function index(Request $request, Organisation $organisation, Brand $brand, ManageBrandMembers $service, ApiResponse $response): JsonResponse
    {
        $filters = $request->validate(['per_page' => ['sometimes', 'integer', 'min:1', 'max:100'], 'search' => ['sometimes', 'string', 'max:100']]);
        $perPage = (int) ($filters['per_page'] ?? 25);

        return $response->success($service->directory($request->user(), $organisation, $brand, $perPage, (string) ($filters['search'] ?? '')));
    }

    public function store(AddBrandMemberRequest $request, Organisation $organisation, Brand $brand, ManageBrandMembers $service, ApiResponse $response): JsonResponse
    {
        $data = $request->validated();
        $service->add($request->user(), $organisation, $brand, (int) $data['user_id'], $data['role']);

        return $response->created(['added' => true]);
    }

    public function update(ChangeBrandMemberRoleRequest $request, Organisation $organisation, Brand $brand, int $userId, ManageBrandMembers $service, ApiResponse $response): JsonResponse
    {
        $service->changeRole($request->user(), $organisation, $brand, $userId, $request->validated('role'));

        return $response->success(['updated' => true]);
    }

    public function destroy(Request $request, Organisation $organisation, Brand $brand, int $userId, ManageBrandMembers $service, ApiResponse $response): JsonResponse
    {
        $service->revoke($request->user(), $organisation, $brand, $userId);

        return $response->success(['revoked' => true]);
    }
}
