<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Requests\BrandStrategy\CreateClaimEvidenceRequest;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\BrandStrategy\GovernBrandClaims;
use Illuminate\Http\RedirectResponse;

final class BrandClaimEvidenceController extends Controller
{
    public function store(CreateClaimEvidenceRequest $request, Organisation $organisation, Brand $brand, string $claimUuid, GovernBrandClaims $service): RedirectResponse
    {
        $service->addEvidence($request->user(), $organisation, $brand, $claimUuid, $request->validated());

        return back()->with('success', 'Evidence attached; it is pending verification.');
    }
}
