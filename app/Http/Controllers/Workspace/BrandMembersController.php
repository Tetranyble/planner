<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Membership\AddBrandMemberRequest;
use App\Http\Requests\Membership\ChangeBrandMemberRoleRequest;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Membership\ManageBrandMembers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class BrandMembersController extends Controller
{
    public function index(Request $request, Organisation $organisation, Brand $brand, ManageBrandMembers $service): Response
    {
        $filters = $request->validate(['per_page' => ['sometimes', 'integer', 'min:1', 'max:100'], 'search' => ['sometimes', 'string', 'max:100']]);
        $pageSize = (int) ($filters['per_page'] ?? 25);

        return Inertia::render('Organisations/Brands/Members', [
            'organisation' => ['uuid' => $organisation->uuid, 'name' => $organisation->name],
            'brand' => ['uuid' => $brand->uuid, 'name' => $brand->name],
            'directory' => $service->directory($request->user(), $organisation, $brand, $pageSize, (string) ($filters['search'] ?? '')),
        ]);
    }

    public function store(AddBrandMemberRequest $request, Organisation $organisation, Brand $brand, ManageBrandMembers $service): RedirectResponse
    {
        $data = $request->validated();
        $service->add($request->user(), $organisation, $brand, (int) $data['user_id'], $data['role']);

        return back()->with('success', 'Member added to brand.');
    }

    public function update(ChangeBrandMemberRoleRequest $request, Organisation $organisation, Brand $brand, int $userId, ManageBrandMembers $service): RedirectResponse
    {
        $service->changeRole($request->user(), $organisation, $brand, $userId, $request->validated('role'));

        return back()->with('success', 'Brand membership role updated.');
    }

    public function destroy(Request $request, Organisation $organisation, Brand $brand, int $userId, ManageBrandMembers $service): RedirectResponse
    {
        $service->revoke($request->user(), $organisation, $brand, $userId);

        return back()->with('success', 'Brand access revoked.');
    }
}
