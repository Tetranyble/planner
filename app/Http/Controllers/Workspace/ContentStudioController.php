<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Content\ManageContentApprovals;
use App\Services\Content\ManageContentStudio;
use App\Services\Creative\CanonicalCreativeImageUpload;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ContentStudioController extends Controller
{
    public function index(Request $request, Organisation $organisation, Brand $brand, ManageContentStudio $service): Response
    {
        return Inertia::render('Organisations/Brands/Content/Index', [
            'organisation' => ['uuid' => $organisation->uuid, 'name' => $organisation->name],
            'brand' => ['uuid' => $brand->uuid, 'name' => $brand->name],
            'studio' => $service->listing($request->user(), $organisation, $brand),
            'can' => [
                'create' => $service->can($request->user(), $organisation, $brand, 'content_item.store'),
                'media' => $service->can($request->user(), $organisation, $brand, 'media_asset.store'),
                'creativeImageEnabled' => (bool) config('planner.creative_image_enabled', false),
                'creativeAutoStorageEnabled' => CanonicalCreativeImageUpload::isReleased(),
                'reviewCampaign' => $service->can($request->user(), $organisation, $brand, 'approval_request.update'),
            ],
        ]);
    }

    public function show(Request $request, Organisation $organisation, Brand $brand, string $itemUuid, ManageContentStudio $service, ManageContentApprovals $approvals): Response
    {
        return Inertia::render('Organisations/Brands/Content/Show', [
            'organisation' => ['uuid' => $organisation->uuid, 'name' => $organisation->name],
            'brand' => ['uuid' => $brand->uuid, 'name' => $brand->name],
            'studio' => $service->detail($request->user(), $organisation, $brand, $itemUuid),
            'approvals' => $approvals->can($request->user(), $organisation, $brand, 'approval_request.index')
                ? $approvals->listing($request->user(), $organisation, $brand, $itemUuid) : [],
            'actorId' => (int) $request->user()->id,
            'can' => [
                'revise' => $service->can($request->user(), $organisation, $brand, 'content_version.store'),
                'target' => $service->can($request->user(), $organisation, $brand, 'content_target.store'),
                'requestApproval' => $approvals->can($request->user(), $organisation, $brand, 'approval_request.store'),
                'reviewApproval' => $approvals->can($request->user(), $organisation, $brand, 'content.approve'),
                'withdrawApproval' => $approvals->can($request->user(),$organisation,$brand,'approval_request.update'),
            ],
        ]);
    }
}
