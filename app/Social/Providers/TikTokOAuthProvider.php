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

final class TikTokOAuthProvider implements RefreshableSocialProvider, SocialOAuthProvider
{
    public function key(): string
    {
        return 'tiktok';
    }

    public function authorizationUrl(string $state, string $redirectUri): string
    {
        return 'https://www.tiktok.com/v2/auth/authorize/?'.http_build_query([
            'client_key' => config('social_integrations.providers.tiktok.client_id'),
            'response_type' => 'code', 'scope' => 'user.info.basic',
            'redirect_uri' => $redirectUri, 'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function exchange(string $code, string $redirectUri, ?string $state = null): OAuthGrant
    {
        $body = Http::asForm()->acceptJson()->timeout(15)->post('https://open.tiktokapis.com/v2/oauth/token/', [
            'client_key' => config('social_integrations.providers.tiktok.client_id'),
            'client_secret' => config('social_integrations.providers.tiktok.client_secret'),
            'code' => $code, 'grant_type' => 'authorization_code', 'redirect_uri' => $redirectUri,
        ])->throw()->json();
        if (isset($body['error']) || ! is_string($body['access_token'] ?? null)
            || ! is_string($body['open_id'] ?? null) || ! is_string($body['refresh_token'] ?? null)) {
            throw new RuntimeException('TikTok authorization could not be completed.');
        }

        return new OAuthGrant(
            $body['access_token'], $body['refresh_token'], $body['open_id'],
            array_values(array_filter(explode(',', $body['scope'] ?? ''))),
            (int) ($body['expires_in'] ?? 0),
            (int) ($body['refresh_expires_in'] ?? 0),
        );
    }

    public function refresh(string $refreshToken): TokenRefreshResult
    {
        try {
            $response = Http::asForm()->acceptJson()->timeout(15)->post('https://open.tiktokapis.com/v2/oauth/token/', ['client_key' => config('social_integrations.providers.tiktok.client_id'), 'client_secret' => config('social_integrations.providers.tiktok.client_secret'), 'grant_type' => 'refresh_token', 'refresh_token' => $refreshToken]);
            $result = RefreshTokenResponse::parse($response, true, ',');
            if ($result->actorId === null || $result->actorId === '') {
                throw new TokenRefreshFailure('INVALID_PROVIDER_RESPONSE');
            }

            return $result;
        } catch (ConnectionException $exception) {
            throw new TokenRefreshFailure('PROVIDER_OUTCOME_UNCERTAIN');
        }
    }

    public function discover(OAuthGrant $grant): array
    {
        $body = Http::withToken($grant->accessToken)->acceptJson()->timeout(15)
            ->get('https://open.tiktokapis.com/v2/user/info/', [
                'fields' => 'open_id,display_name,avatar_url',
            ])->throw()->json();
        $user = $body['data']['user'] ?? null;
        if (($body['error']['code'] ?? null) !== 'ok' || ! is_array($user)
            || ($user['open_id'] ?? null) !== $grant->actorId) {
            throw new RuntimeException('TikTok profile identity verification failed.');
        }

        return [new DiscoveredSocialAccount(
            $user['open_id'], 'CREATOR', (string) ($user['display_name'] ?? 'TikTok account'),
        )];
    }

    public function capabilities(OAuthGrant $grant, DiscoveredSocialAccount $account): array
    {
        return [
            'profile.read' => ['mode' => 'READ_ONLY', 'reason' => 'TikTok user.info.basic consent granted'],
            'video.publish' => ['mode' => 'ASSISTED', 'reason' => 'Direct publishing unavailable until TikTok audit and publish scopes are approved'],
        ];
    }
}
