<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Social\ManageSocialConnections;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Separate command controller: never authorize solely by callback possession. */
final class ConfirmSocialOAuthSelectionController extends Controller
{
    public function __invoke(Request $request, Organisation $organisation, Brand $brand,
        string $selectionUuid, ManageSocialConnections $service): RedirectResponse
    {
        $data = $request->validate([
            'account_ids' => ['required', 'array', 'min:1', 'max:100'],
            'account_ids.*' => ['required', 'string', 'max:255', 'distinct'],
        ]);
        $service->confirmSelection($request->user(), $organisation, $brand, $selectionUuid, $data['account_ids']);

        return redirect()->route('organisations.brands.social-accounts.index', [
            'organisation' => $organisation->uuid,
            'brand' => $brand->uuid,
        ])->with('success', 'Selected social accounts connected successfully.');
    }
}
