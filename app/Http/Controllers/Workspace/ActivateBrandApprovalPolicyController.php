<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\BrandStrategy\GovernBrandClaims;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ActivateBrandApprovalPolicyController extends Controller
{
    public function __invoke(Request $request, Organisation $organisation, Brand $brand, string $policyUuid, GovernBrandClaims $service): RedirectResponse
    {
        $service->activateApprovalPolicy($request->user(), $organisation, $brand, $policyUuid);

        return back()->with('success', 'Approval policy is now active.');
    }
}
