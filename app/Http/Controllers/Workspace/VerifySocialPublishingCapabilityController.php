<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Publishing\VerifySocialPublishingCapability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class VerifySocialPublishingCapabilityController extends Controller
{
    public function __invoke(Request $request, Organisation $organisation, Brand $brand,
        string $accountUuid, VerifySocialPublishingCapability $service): RedirectResponse
    {
        $result = $service->verify($request->user(), $organisation, $brand, $accountUuid);

        return back()->with($result['mode'] === 'AUTOMATIC' ? 'success' : 'warning',
            $result['mode'] === 'AUTOMATIC'
                ? 'Publishing permission verified temporarily. Approval and release gates still apply.'
                : 'Provider account identity or permission could not be verified. Publishing remains unavailable.');
    }
}
