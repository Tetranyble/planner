<?php

namespace App\Http\Controllers\OAuth;

use App\Models\User;
use App\Services\OAuth\ManagePlannerOAuthLinks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class RevokePlannerOAuthLinkController
{
    public function __invoke(Request $request, ManagePlannerOAuthLinks $links, int $linkId): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        abort_unless($links->revoke($actor, $linkId), 404);

        return redirect()->route('planner.oauth.links.index')->with('status', 'Connection disconnected.');
    }
}
