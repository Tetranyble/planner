<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Social\ManageSocialConnections;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/** Non-CRUD OAuth initiation is an invokable action. */
final class StartSocialOAuthController extends Controller
{
    public function __invoke(Request $request, Organisation $organisation, Brand $brand,
        string $provider, ManageSocialConnections $service): HttpResponse
    {
        $authorizationUrl = $service->start($request->user(), $organisation, $brand, $provider);

        return $request->hasHeader('X-Inertia')
            ? Inertia::location($authorizationUrl)
            : Redirect::away($authorizationUrl);
    }
}
