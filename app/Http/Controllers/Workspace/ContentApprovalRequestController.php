<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Content\SubmitContentApprovalRequest;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Content\ManageContentApprovals;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ContentApprovalRequestController extends Controller
{
    public function index(Request $request, Organisation $organisation, Brand $brand, string $itemUuid, ManageContentApprovals $service): Response
    {
        return Inertia::render('Organisations/Brands/Content/Approvals', [
            'organisation' => ['uuid' => $organisation->uuid, 'name' => $organisation->name],
            'brand' => ['uuid' => $brand->uuid, 'name' => $brand->name],
            'itemUuid' => $itemUuid, 'approvals' => $service->listing($request->user(), $organisation, $brand, $itemUuid),
        ]);
    }

    public function store(SubmitContentApprovalRequest $request, Organisation $organisation, Brand $brand, string $itemUuid, string $targetUuid, ManageContentApprovals $service): RedirectResponse
    {
        $service->submit($request->user(), $organisation, $brand, $itemUuid, $targetUuid, $request->validated('expected_target_hash'));

        return back()->with('success','Content review requested.');
    }
}
