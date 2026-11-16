<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Membership\InviteOrganisationMemberRequest;
use App\Models\Organisation;
use App\Services\Membership\ManageOrganisationMembers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class OrganisationInvitationManagementController extends Controller
{
    public function store(InviteOrganisationMemberRequest $request, Organisation $organisation, ManageOrganisationMembers $service): RedirectResponse
    {
        $data = $request->validated();
        $service->invite($request->user(), $organisation, $data['email'], $data['role']);

        return back()->with('success', 'Invitation queued for delivery.');
    }

    public function destroy(Request $request, Organisation $organisation, string $uuid, ManageOrganisationMembers $service): RedirectResponse
    {
        $service->cancelInvitation($request->user(), $organisation, $uuid);

        return back()->with('success', 'Invitation cancelled.');
    }
}
