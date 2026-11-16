<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Content\ManageContentApprovals;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class WithdrawContentApprovalController extends Controller
{
    public function __invoke(Request $request, Organisation $organisation, Brand $brand, string $itemUuid, int $requestId, ManageContentApprovals $service): RedirectResponse
    {
        $service->withdraw($request->user(), $organisation, $brand, $itemUuid, $requestId);

        return back()->with('success', 'Review withdrawn.');
    }
}
