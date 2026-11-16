<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Social\DecideGrantReconciliationRequest;
use App\Models\Organisation;
use App\Services\Social\ReconcileGrantRevocations;
use Illuminate\Http\RedirectResponse;

final class DecideGrantReconciliationController extends Controller
{
    public function __invoke(DecideGrantReconciliationRequest $request, Organisation $organisation,
        string $requestUuid, ReconcileGrantRevocations $service): RedirectResponse
    {
        $service->decide($organisation, $request->user(), $requestUuid, $request->validated());

        return back()->with('success', 'Independent review recorded. Remote provider acknowledgement was not inferred.');
    }
}
