<?php

namespace App\Http\Controllers\OAuth;

use App\Models\User;
use App\Services\OAuth\ManagePlannerOAuthLinks;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ListPlannerOAuthLinksController
{
    public function __invoke(Request $request, ManagePlannerOAuthLinks $links): View
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return view('oauth.planner-agent-links', ['links' => $links->listing($actor)]);
    }
}
