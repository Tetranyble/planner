<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Requests\BrandStrategy\ReviewClaimRequest;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\BrandStrategy\GovernBrandClaims;
use Illuminate\Http\RedirectResponse;

final class ReviewBrandClaimController extends Controller
{
    public function __invoke(ReviewClaimRequest $request, Organisation $organisation, Brand $brand, string $claimUuid, GovernBrandClaims $service): RedirectResponse
    {
        $data = $request->validated();
        $service->reviewClaim($request->user(), $organisation, $brand, $claimUuid, $data['decision'], $data['rationale']);

        return back()->with('success', 'Claim decision recorded.');
    }
}
