<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Requests\BrandStrategy\ReviewSourceRequest;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\BrandStrategy\GovernBrandClaims;
use Illuminate\Http\RedirectResponse;

final class ReviewBrandKnowledgeSourceController extends Controller
{
    public function __invoke(ReviewSourceRequest $request, Organisation $organisation, Brand $brand, string $sourceUuid, GovernBrandClaims $service): RedirectResponse
    {
        $data = $request->validated();
        $service->reviewSource($request->user(), $organisation, $brand, $sourceUuid, $data['decision'], $data['rationale']);

        return back()->with('success', 'Source review recorded.');
    }
}
