<?php

namespace App\Http\Controllers\OAuth;

use App\Models\User;
use App\Services\OAuth\ManagePlannerOAuthLinks;
use Illuminate\Http\Request;
use Laravel\Passport\Http\Controllers\ApproveAuthorizationController;
use Symfony\Component\HttpFoundation\Response;

/** Explicit first-party consent; the Passport library only signs the validated code. */
final class ApprovePlannerOAuthConsentController
{
    public function __invoke(Request $request, ApproveAuthorizationController $passport, ManagePlannerOAuthLinks $consent): Response
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->hasVerifiedEmail(), 403);
        $data = $request->validate([
            'client_id' => ['required', 'string', 'max:160'],
            'auth_token' => ['required', 'string', 'min:16', 'max:256'],
            'delegation_id' => ['required', 'integer', 'min:1'],
        ]);
        abort_unless(hash_equals((string) $request->session()->get('planner_oauth_client_id', ''), $data['client_id'])
            && hash_equals((string) $request->session()->get('authToken', ''), $data['auth_token']),
            403, 'OAuth session or consent token mismatch.');
        $consent->approve($actor, $data['client_id'], (int) $data['delegation_id']);
        $request->session()->forget('planner_oauth_client_id');

        // Passport consumes the session-bound authorization request and completes PKCE grant.
        return app()->call([$passport, 'approve']);
    }
}
