<?php

namespace App\Social\Providers;

use App\Social\Contracts\RefreshableSocialProvider;
use App\Social\Contracts\SocialOAuthProvider;
use App\Social\Data\DiscoveredSocialAccount;
use App\Social\Data\OAuthGrant;
use App\Social\Data\TokenRefreshResult;
use App\Social\Exceptions\TokenRefreshFailure;
use App\Social\Support\RefreshTokenResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** X OAuth 2.0 Authorization Code + PKCE: verifier derived server-side from single-use state. */
final class XOAuthProvider implements RefreshableSocialProvider, SocialOAuthProvider
{
    public function key(): string
    {
        return 'x';
    }

    private function verifier(string $state): string
    {
        if (strlen($state) !== 64 || ! ctype_alnum($state)) {
            throw new RuntimeException('Invalid X OAuth state.');
        }
        $secret = (string) config('app.key');
        if (strlen($secret) < 32) {
            throw new RuntimeException('APP_KEY must be configured for OAuth PKCE.');
        }

        return rtrim(strtr(base64_encode(hash_hmac('sha256', $state, $secret, true)), '+/', '-_'), '=');
    }

    private function scopes(): string
    {
        $scopes = ['tweet.read', 'users.read', 'offline.access'];
        if (config('social_integrations.publishing_entitlements.x', false)) {
            $scopes[] = 'tweet.write';
        }

        return implode(' ', $scopes);
    }

    public function authorizationUrl(string $state, string $redirectUri): string
    {
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $this->verifier($state), true)), '+/', '-_'), '=');

        return 'https://x.com/i/oauth2/authorize?'.http_build_query([
            'response_type' => 'code', 'client_id' => config('social_integrations.providers.x.client_id'),
            'redirect_uri' => $redirectUri, 'scope' => $this->scopes(),
            'state' => $state, 'code_challenge' => $challenge, 'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function exchange(string $code, string $redirectUri, ?string $state = null): OAuthGrant
    {
        if ($state === null) {
            throw new RuntimeException('X PKCE requires original authorization state.');
        }
        $body = Http::asForm()->withBasicAuth(
            (string) config('social_integrations.providers.x.client_id'),
            (string) config('social_integrations.providers.x.client_secret'),
        )->acceptJson()->timeout(15)->post('https://api.x.com/2/oauth2/token', [
            'grant_type' => 'authorization_code', 'code' => $code,
            'redirect_uri' => $redirectUri, 'code_verifier' => $this->verifier($state),
        ])->throw()->json();
        if (! is_string($body['access_token'] ?? null) || $body['access_token'] === '') {
            throw new RuntimeException('X OAuth token exchange failed.');
        }
        $user = Http::withToken($body['access_token'])->acceptJson()->timeout(15)
            ->get('https://api.x.com/2/users/me', ['user.fields' => 'profile_image_url'])->throw()->json();
        if (! is_string($user['data']['id'] ?? null) || $user['data']['id'] === '') {
            throw new RuntimeException('X profile identity verification failed.');
        }

        return new OAuthGrant($body['access_token'], $body['refresh_token'] ?? null, $user['data']['id'],
            array_values(array_filter(explode(' ', (string) ($body['scope'] ?? 'tweet.read users.read')))),
            (int) ($body['expires_in'] ?? 0));
    }

    public function refresh(string $refreshToken): TokenRefreshResult
    {
        try {
            $response = Http::asForm()->withBasicAuth(
                (string) config('social_integrations.providers.x.client_id'),
                (string) config('social_integrations.providers.x.client_secret'),
            )->acceptJson()->timeout(15)->post('https://api.x.com/2/oauth2/token', [
                'grant_type' => 'refresh_token', 'refresh_token' => $refreshToken,
            ]);

            return RefreshTokenResponse::parse($response, true);
        } catch (ConnectionException $exception) {
            throw new TokenRefreshFailure('PROVIDER_OUTCOME_UNCERTAIN');
        }
    }

    public function discover(OAuthGrant $grant): array
    {
        $user = Http::withToken($grant->accessToken)->acceptJson()->timeout(15)
            ->get('https://api.x.com/2/users/me', ['user.fields' => 'profile_image_url'])->throw()->json();
        $data = $user['data'] ?? [];
        if (! is_string($data['id'] ?? null) || $data['id'] !== $grant->actorId) {
            throw new RuntimeException('X account identity does not match the authorised user.');
        }

        return [new DiscoveredSocialAccount(
            $data['id'], 'CREATOR', (string) ($data['name'] ?? $data['username'] ?? 'X account'),
            isset($data['username']) ? (string) $data['username'] : null,
        )];
    }

    public function capabilities(OAuthGrant $grant, DiscoveredSocialAccount $account): array
    {
        return [
            'profile.read' => ['mode' => 'READ_ONLY', 'reason' => 'X OAuth users.read consent granted'],
            'post.publish' => ['mode' => 'UNAVAILABLE', 'reason' => 'tweet.write consent, API billing and publishing service not yet enabled'],
        ];
    }
}
