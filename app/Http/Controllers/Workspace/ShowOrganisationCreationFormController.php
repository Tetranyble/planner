<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class ShowOrganisationCreationFormController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Organisations/Create');
    }
}
