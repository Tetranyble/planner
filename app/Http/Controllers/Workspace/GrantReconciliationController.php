<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Social\StoreGrantReconciliationEvidenceRequest;
use App\Models\Organisation;
use App\Services\Social\ReconcileGrantRevocations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class GrantReconciliationController extends Controller
{
    public function index(Request $request, Organisation $organisation, ReconcileGrantRevocations $service): Response
    {
        return Inertia::render('Organisations/Social/GrantReconciliations', [
            'organisation' => $organisation->only(['uuid', 'name']),
            'cases' => $service->directory($organisation, $request->user()),
        ]);
    }

    public function store(StoreGrantReconciliationEvidenceRequest $request, Organisation $organisation,
        string $requestUuid, ReconcileGrantRevocations $service): RedirectResponse
    {
        $service->submit($organisation, $request->user(), $requestUuid, $request->validated());

        return back()->with('success', 'Evidence recorded. A different owner must verify it.');
    }
}
