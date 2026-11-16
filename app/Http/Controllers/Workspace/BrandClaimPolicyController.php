<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Requests\BrandStrategy\UpdateClaimPolicyRequest;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\BrandStrategy\ManageBrandStrategy;
use Illuminate\Http\RedirectResponse;

final class BrandClaimPolicyController extends Controller
{
    public function update(UpdateClaimPolicyRequest $request, Organisation $organisation, Brand $brand, ManageBrandStrategy $service): RedirectResponse
    {
        $service->updateClaimPolicy($request->user(), $organisation, $brand, $request->validated());

        return back()->with('success', 'Claims policy updated.');
    }
}
