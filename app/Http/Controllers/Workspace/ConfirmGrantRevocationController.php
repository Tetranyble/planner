<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Organisation;
use App\Services\Social\ManageGrantRevocations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ConfirmGrantRevocationController extends Controller
{
    public function __invoke(Request $request, Organisation $organisation, string $requestUuid,
        ManageGrantRevocations $service): RedirectResponse
    {
        $result = $service->confirm($organisation, $request->user(), $requestUuid);

        return back()->with('success', $result['status'] === 'READY'
            ? 'Revocation confirmed and queued; external processing remains disabled until configured.'
            : 'Provider cannot be revoked automatically. Follow manual provider instructions.');
    }
}
