<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Requests\BrandStrategy\UpdateBrandProfileRequest;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\BrandStrategy\ManageBrandStrategy;
use Illuminate\Http\RedirectResponse;

final class BrandProfileController extends Controller
{
    public function update(UpdateBrandProfileRequest $request, Organisation $organisation, Brand $brand, ManageBrandStrategy $service): RedirectResponse
    {
        $service->updateProfile($request->user(), $organisation, $brand, $request->validated());

        return back()->with('success', 'Brand positioning updated.');
    }
}
