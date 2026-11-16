<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Content\DecideContentApprovalRequest;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Content\ManageContentApprovals;
use Illuminate\Http\RedirectResponse;

final class ContentApprovalDecisionController extends Controller
{
    public function store(DecideContentApprovalRequest $request, Organisation $organisation, Brand $brand, string $itemUuid, int $requestId, ManageContentApprovals $service): RedirectResponse
    {
        $data = $request->validated();
        $service->decide($request->user(), $organisation, $brand, $itemUuid, $requestId, $data['decision'], $data['comment']);

        return back()->with('success','Review decision recorded.');
    }
}
