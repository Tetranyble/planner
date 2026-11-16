<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Services\Membership\ManageOrganisationMembers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AcceptOrganisationInvitationController extends Controller
{
    public function __invoke(Request $request, string $token, ManageOrganisationMembers $service): RedirectResponse
    {
        $organisation = $service->accept($request->user(), $token);

        return redirect()->route('organisations.brands.index', ['organisation' => $organisation->uuid])
            ->with('success', 'You have joined the organisation.');
    }
}
