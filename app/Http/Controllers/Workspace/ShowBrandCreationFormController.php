<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Organisation;
use App\Services\Onboarding\CreateBrandService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ShowBrandCreationFormController extends Controller
{
    public function __invoke(Request $request, Organisation $organisation, CreateBrandService $service): Response
    {
        if (! $service->canCreate($request->user(), $organisation)) {
            throw new AuthorizationException('Not permitted to create a brand in this organisation.');
        }

        return Inertia::render('Organisations/Brands/Create', [
            'organisation' => [
                'uuid' => $organisation->uuid,
                'name' => $organisation->name,
                'timezone' => $organisation->timezone instanceof \BackedEnum
                    ? $organisation->timezone->value : $organisation->timezone,
            ],
        ]);
    }
}
