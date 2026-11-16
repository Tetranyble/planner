<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Services\Social\ManageSocialConnections;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Static callback URL per provider; tenant/brand recovered exclusively from one-time state. */
final class CompleteSocialOAuthController extends Controller
{
    public function __invoke(Request $request, string $provider, ManageSocialConnections $service): RedirectResponse
    {
        $data = $request->validate([
            'state' => ['required', 'string', 'size:64', 'alpha_num:ascii'],
            'code' => ['nullable', 'string', 'max:4096'],
            'error' => ['nullable', 'string', 'max:128'],
        ]);
        $result = $service->complete($request->user(), $provider, $data['state'],
            $data['code'] ?? null, $data['error'] ?? null);
        if (isset($result['selection_uuid'])) {
            return redirect()->route('organisations.brands.social-selections.show', [
                'organisation' => $result['organisation_uuid'],
                'brand' => $result['brand_uuid'],
                'selectionUuid' => $result['selection_uuid'],
            ]);
        }

        return redirect()->route('organisations.brands.social-accounts.index', [
            'organisation' => $result['organisation_uuid'], 'brand' => $result['brand_uuid'],
        ])->with('success', 'Social accounts connected. Publishing capabilities are restricted until separately enabled.');
    }
}
