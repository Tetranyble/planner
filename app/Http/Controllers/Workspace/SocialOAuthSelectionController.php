<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Social\ManageSocialConnections;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class SocialOAuthSelectionController extends Controller
{
    public function show(Request $request, Organisation $organisation, Brand $brand,
        string $selectionUuid, ManageSocialConnections $service): Response
    {
        return Inertia::render('Organisations/Brands/SocialAccountSelection', [
            'organisation' => $organisation->only('uuid', 'name'),
            'brand' => $brand->only('uuid', 'name'),
            'selection' => $service->selection($request->user(), $organisation, $brand, $selectionUuid),
        ]);
    }
}
