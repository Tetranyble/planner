<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Membership\ChangeOrganisationMemberRoleRequest;
use App\Models\Organisation;
use App\Services\Membership\ManageOrganisationMembers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class OrganisationMembersController extends Controller
{
    public function index(Request $request, Organisation $organisation, ManageOrganisationMembers $service): Response
    {
        $perPage = $request->validate(['per_page' => ['sometimes', 'integer', 'min:1', 'max:100']])['per_page'] ?? 25;

        return Inertia::render('Organisations/Members', [
            'organisation' => ['uuid' => $organisation->uuid, 'name' => $organisation->name],
            'directory' => $service->directory($request->user(), $organisation, (int) $perPage),
            'currentUserId' => (int) $request->user()->getKey(),
        ]);
    }

    public function update(ChangeOrganisationMemberRoleRequest $request, Organisation $organisation, int $userId, ManageOrganisationMembers $service): RedirectResponse
    {
        $service->changeRole($request->user(), $organisation, $userId, $request->validated('role'));

        return back()->with('success', 'Membership updated.');
    }

    public function destroy(Request $request, Organisation $organisation, int $userId, ManageOrganisationMembers $service): RedirectResponse
    {
        $service->revoke($request->user(), $organisation, $userId);

        return back()->with('success', 'Membership revoked.');
    }
}
