<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Requests\BrandStrategy\CreateDraftClaimRequest;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\BrandStrategy\ManageBrandStrategy;
use Illuminate\Http\RedirectResponse;

final class BrandClaimController extends Controller
{
    public function store(CreateDraftClaimRequest $request, Organisation $organisation, Brand $brand, ManageBrandStrategy $service): RedirectResponse
    {
        $service->addDraftClaim($request->user(), $organisation, $brand, $request->validated());

        return back()->with('success', 'Claim saved as draft; not approved for publication.');
    }
}
