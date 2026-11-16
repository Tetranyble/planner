<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Social\PrepareGrantRevocationRequest;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Social\ManageGrantRevocations;
use Illuminate\Http\RedirectResponse;

final class GrantRevocationController extends Controller
{
    public function store(PrepareGrantRevocationRequest $request, Organisation $organisation, Brand $brand,
        string $accountUuid, ManageGrantRevocations $service): RedirectResponse
    {
        $result = $service->prepare($organisation, $brand, $request->user(), $accountUuid, $request->validated('reason'));

        return back()->with('success', sprintf('Grant preview ready: %d linked connection(s). Review and confirm separately.', $result['affected_connections']));
    }
}
