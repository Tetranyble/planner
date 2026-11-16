<?php

namespace App\Http\Controllers\OAuth;

use Illuminate\Http\Request;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use Symfony\Component\HttpFoundation\Response;

/**
 * The OAuth token URL is application-owned; Passport handles signed token exchange.
 * Disable grants not needed for interactive ChatGPT authorization-code + PKCE.
 */
final class IssuePlannerOAuthTokenController
{
    public function __invoke(Request $request, AccessTokenController $passport): Response
    {
        abort_unless(in_array((string) $request->input('grant_type'),
            ['authorization_code', 'refresh_token'], true), 400, 'Unsupported OAuth grant type.');
        if ($request->input('grant_type') === 'authorization_code') {
            abort_unless(is_string($request->input('code_verifier'))
                && preg_match('/\A[A-Za-z0-9._~-]{43,128}\z/D', $request->input('code_verifier')) === 1,
                400, 'PKCE verifier required.');
        }

        return app()->call([$passport, 'issueToken']);
    }
}
