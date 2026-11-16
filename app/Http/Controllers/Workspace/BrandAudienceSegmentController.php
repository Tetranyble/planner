<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Requests\BrandStrategy\CreateAudienceRequest;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\BrandStrategy\ManageBrandStrategy;
use Illuminate\Http\RedirectResponse;

final class BrandAudienceSegmentController extends Controller
{
    public function store(CreateAudienceRequest $request, Organisation $organisation, Brand $brand, ManageBrandStrategy $service): RedirectResponse
    {
        $service->addAudience($request->user(), $organisation, $brand, $request->validated());

        return back()->with('success', 'Audience segment created.');
    }
}
