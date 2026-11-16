<?php

namespace App\Social\Support;

use App\Social\Data\TokenRefreshResult;
use App\Social\Exceptions\TokenRefreshFailure;
use Illuminate\Http\Client\Response;

/** Parse only well-known OAuth fields; do not preserve/log provider payloads or credentials. */
final class RefreshTokenResponse
{
    public static function parse(Response $response, bool $rotates, string $scopeSeparator = ' '): TokenRefreshResult
    {
        $body = $response->json();
        if (! is_array($body)) {
            throw new TokenRefreshFailure('INVALID_PROVIDER_RESPONSE');
        }
        if ($response->status() === 429) {
            throw new TokenRefreshFailure('RATE_LIMIT', true);
        }
        $providerError = $body['error'] ?? null;
        $hasError = $providerError !== null && $providerError !== 'ok'
            && (! is_array($providerError) || ($providerError['code'] ?? '') !== 'ok');
        if (! $response->successful() || $hasError) {
            // Any failed refresh other than an explicit 429 could have consumed a rotating token.
            $error = is_array($body['error'] ?? null) ? ($body['error']['code'] ?? '') : ($body['error'] ?? '');
            $code = in_array($error, ['invalid_grant', 'invalid_token', 'invalid_request', 'access_denied'], true)
                ? 'INVALID_GRANT' : 'PROVIDER_OUTCOME_UNCERTAIN';
            throw new TokenRefreshFailure($code);
        }
        $access = $body['access_token'] ?? null;
        $refresh = $body['refresh_token'] ?? null;
        $ttl = $body['expires_in'] ?? null;
        if (! is_string($access) || $access === '' || ! is_numeric($ttl) || (int) $ttl <= 0
            || ($refresh !== null && (! is_string($refresh) || $refresh === ''))
            || ($rotates && ! is_string($refresh))) {
            throw new TokenRefreshFailure('INVALID_PROVIDER_RESPONSE');
        }
        $scopes = null;
        if (isset($body['scope']) && is_string($body['scope'])) {
            $scopes = array_values(array_filter(explode($scopeSeparator, trim($body['scope']))));
        }
        $refreshExpires = isset($body['refresh_expires_in']) && is_numeric($body['refresh_expires_in'])
            ? (int) $body['refresh_expires_in'] : null;
        if ($refreshExpires === null && isset($body['refresh_token_expires_in']) && is_numeric($body['refresh_token_expires_in'])) {
            $refreshExpires = (int) $body['refresh_token_expires_in'];
        }

        return new TokenRefreshResult($access, $refresh, (int) $ttl, $refreshExpires, $scopes,
            is_string($body['open_id'] ?? null) ? $body['open_id'] : null);
    }
}
