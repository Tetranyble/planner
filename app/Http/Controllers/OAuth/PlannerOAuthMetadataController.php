<?php

namespace App\Http\Controllers\OAuth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Discovery endpoints are application-authored, never silently registered by Passport/MCP. */
final class PlannerOAuthMetadataController
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless(config('planner.oauth_enabled', false), 404);
        $path = trim((string) $request->route('path', ''), '/');
        $resource = 'mcp/planner-agent-oauth';
        if ($request->is('.well-known/oauth-protected-resource*')) {
            abort_unless($path === '' || $path === $resource, 404);

            return response()->json([
                'resource' => url('/'.$resource),
                'authorization_servers' => [url('/')],
                'scopes_supported' => ['mcp:use'],
            ])->header('Cache-Control', 'public, max-age=120');
        }
        $metadata = [
            'issuer' => rtrim(url('/'), '/'),
            'authorization_endpoint' => route('passport.authorizations.authorize'),
            'token_endpoint' => route('passport.token'),
            'registration_endpoint' => config('planner.oauth_dcr_enabled', false) ? route('passport.mcp.registration') : null,
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'scopes_supported' => ['mcp:use'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['none'],
        ];

        return response()->json(array_filter($metadata, static fn (mixed $value): bool => $value !== null),
            200, ['Cache-Control' => 'public, max-age=120']);
    }
}
