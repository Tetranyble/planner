<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\CreateBrandRequest;
use App\Models\Organisation;
use App\Services\Onboarding\CreateBrandService;
use Illuminate\Http\RedirectResponse;

final class BrandOnboardingController extends Controller
{
    public function store(
        CreateBrandRequest $request,
        Organisation $organisation,
        CreateBrandService $service,
    ): RedirectResponse {
        $attributes = $request->validated();
        $service->execute(
            $request->user(),
            $organisation,
            $attributes['name'],
            $attributes['website_url'] ?? null,
            $attributes['timezone'] ?? null,
        );

        return redirect()->route('organisations.brands.index', ['organisation' => $organisation->uuid]);
    }
}
