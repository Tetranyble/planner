<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Requests\BrandStrategy\ReviewEvidenceRequest;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\BrandStrategy\GovernBrandClaims;
use Illuminate\Http\RedirectResponse;

final class ReviewBrandClaimEvidenceController extends Controller
{
    public function __invoke(ReviewEvidenceRequest $request, Organisation $organisation, Brand $brand, string $evidenceUuid, GovernBrandClaims $service): RedirectResponse
    {
        $data = $request->validated();
        $service->reviewEvidence($request->user(), $organisation, $brand, $evidenceUuid, $data['decision'], $data['rationale']);

        return back()->with('success', 'Evidence review recorded.');
    }
}
