<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\CreateOrganisationRequest;
use App\Services\Onboarding\CreateOrganisationService;
use Illuminate\Http\RedirectResponse;

final class OrganisationOnboardingController extends Controller
{
    public function store(CreateOrganisationRequest $request, CreateOrganisationService $service): RedirectResponse
    {
        $attributes = $request->validated();
        $organisation = $service->execute(
            $request->user(),
            $attributes['name'],
            $attributes['timezone'] ?? 'Africa/Lagos',
        );

        return redirect()->route('organisations.brands.index', ['organisation' => $organisation->uuid]);
    }
}
