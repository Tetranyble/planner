<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Social\ManageGrantRevocations;
use App\Services\Social\ManageSocialConnections;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class BrandSocialAccountController extends Controller
{
    public function index(Request $request, Organisation $organisation, Brand $brand,
        ManageSocialConnections $service, ManageGrantRevocations $revocations): Response
    {
        return Inertia::render('Organisations/Brands/SocialAccounts', [
            'organisation' => $organisation->only(['uuid', 'name']),
            'brand' => $brand->only(['uuid', 'name']),
            'directory' => $service->directory($request->user(), $organisation, $brand),
            'canRevokeGrants' => ($canRevoke = $request->user()->hasRole('owner')
                && $request->user()->hasPermission('social-grant.revoke')),
            'grantRevocations' => $canRevoke ? $revocations->directory($organisation, $request->user()) : [],
            'canVerifyPublishing' => $request->user()->hasPermission('social-account.connect')
                && $request->user()->hasPermission('approval_request.update'),
            'canReviewPrivacy' => $request->user()->hasRole('owner')
                && $request->user()->hasPermission('privacy-deletion.review'),
        ]);
    }

    public function destroy(Request $request, Organisation $organisation, Brand $brand,
        string $accountUuid, ManageSocialConnections $service): RedirectResponse
    {
        $service->disconnect($request->user(), $organisation, $brand, $accountUuid);

        return back()->with('success', 'Social account disconnected locally; revoke the grant at the provider if required.');
    }
}
