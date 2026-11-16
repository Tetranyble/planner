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

final class YouTubeOAuthProvider implements RefreshableSocialProvider, SocialOAuthProvider
{
    public function key(): string
    {
        return 'youtube';
    }

    public function authorizationUrl(string $state, string $redirectUri): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('social_integrations.providers.youtube.client_id'),
            'response_type' => 'code', 'redirect_uri' => $redirectUri,
            'scope' => 'https://www.googleapis.com/auth/youtube.readonly',
            'access_type' => 'offline', 'state' => $state,
            'include_granted_scopes' => 'true',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function exchange(string $code, string $redirectUri, ?string $state = null): OAuthGrant
    {
        $body = Http::asForm()->acceptJson()->timeout(15)->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('social_integrations.providers.youtube.client_id'),
            'client_secret' => config('social_integrations.providers.youtube.client_secret'),
            'code' => $code, 'grant_type' => 'authorization_code', 'redirect_uri' => $redirectUri,
        ])->throw()->json();
        if (! is_string($body['access_token'] ?? null)) {
            throw new RuntimeException('Google authorization could not be completed.');
        }

        // Google does not guarantee a new refresh token on subsequent grants.
        return new OAuthGrant($body['access_token'], $body['refresh_token'] ?? null, '',
            array_values(array_filter(explode(' ', $body['scope'] ?? ''))), (int) ($body['expires_in'] ?? 0));
    }

    public function refresh(string $refreshToken): TokenRefreshResult
    {
        try {
            $response = Http::asForm()->acceptJson()->timeout(15)->post('https://oauth2.googleapis.com/token', ['client_id' => config('social_integrations.providers.youtube.client_id'), 'client_secret' => config('social_integrations.providers.youtube.client_secret'), 'grant_type' => 'refresh_token', 'refresh_token' => $refreshToken]);

            return RefreshTokenResponse::parse($response, false, ' ');
        } catch (ConnectionException $exception) {
            throw new TokenRefreshFailure('PROVIDER_OUTCOME_UNCERTAIN');
        }
    }

    public function discover(OAuthGrant $grant): array
    {
        $body = Http::withToken($grant->accessToken)->acceptJson()->timeout(15)
            ->get('https://www.googleapis.com/youtube/v3/channels', [
                'part' => 'snippet', 'mine' => 'true', 'maxResults' => 50,
            ])->throw()->json();
        $accounts = [];
        foreach ($body['items'] ?? [] as $channel) {
            if (! is_string($channel['id'] ?? null) || $channel['id'] === '') {
                continue;
            }
            $accounts[] = new DiscoveredSocialAccount(
                $channel['id'], 'CHANNEL',
                (string) ($channel['snippet']['title'] ?? 'YouTube channel'),
                $channel['snippet']['customUrl'] ?? null,
            );
        }

        return $accounts;
    }

    public function capabilities(OAuthGrant $grant, DiscoveredSocialAccount $account): array
    {
        return [
            'profile.read' => ['mode' => 'READ_ONLY', 'reason' => 'YouTube read-only OAuth scope granted'],
            'video.publish' => ['mode' => 'UNAVAILABLE', 'reason' => 'Publishing requires separate youtube.upload consent and a publishing implementation'],
        ];
    }
}
