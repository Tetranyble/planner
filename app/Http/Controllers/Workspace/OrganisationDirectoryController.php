<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Resources\Directory\OrganisationSummaryResource;
use App\Services\Directory\DirectoryPage;
use App\Services\Directory\ListOrganisationsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class OrganisationDirectoryController extends Controller
{
    public function __invoke(Request $request, ListOrganisationsService $service): Response
    {
        $perPage = $request->validate(['per_page' => ['sometimes', 'integer', 'min:1', 'max:100']])['per_page'] ?? 20;

        return Inertia::render('Organisations/Index', [
            'organisations' => DirectoryPage::from(
                $service->execute($request->user(), (int) $perPage),
                OrganisationSummaryResource::class,
            ),
        ]);
    }
}
