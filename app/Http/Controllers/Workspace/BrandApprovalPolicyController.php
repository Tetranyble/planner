<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Requests\BrandStrategy\CreateDraftApprovalPolicyRequest;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\BrandStrategy\ManageBrandStrategy;
use Illuminate\Http\RedirectResponse;

final class BrandApprovalPolicyController extends Controller
{
    public function store(CreateDraftApprovalPolicyRequest $request, Organisation $organisation, Brand $brand, ManageBrandStrategy $service): RedirectResponse
    {
        $service->addDraftApprovalPolicy($request->user(), $organisation, $brand, $request->validated());

        return back()->with('success', 'Approval policy draft saved; not active.');
    }
}
