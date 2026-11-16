<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Requests\BrandStrategy\CreateKnowledgeSourceRequest;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\BrandStrategy\GovernBrandClaims;
use Illuminate\Http\RedirectResponse;

final class BrandKnowledgeSourceController extends Controller
{
    public function store(CreateKnowledgeSourceRequest $request, Organisation $organisation, Brand $brand, GovernBrandClaims $service): RedirectResponse
    {
        $service->addSource($request->user(), $organisation, $brand, $request->validated());

        return back()->with('success', 'Source submitted for independent verification.');
    }
}
