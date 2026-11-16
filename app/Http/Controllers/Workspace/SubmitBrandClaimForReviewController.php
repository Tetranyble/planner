<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\BrandStrategy\GovernBrandClaims;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class SubmitBrandClaimForReviewController extends Controller
{
    public function __invoke(Request $request, Organisation $organisation, Brand $brand, string $claimUuid, GovernBrandClaims $service): RedirectResponse
    {
        $service->submitClaim($request->user(), $organisation, $brand, $claimUuid);

        return back()->with('success', 'Claim submitted for review.');
    }
}
