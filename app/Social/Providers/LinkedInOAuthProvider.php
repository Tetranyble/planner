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

final class LinkedInOAuthProvider implements RefreshableSocialProvider, SocialOAuthProvider
{
    public function key(): string
    {
        return 'linkedin';
    }

    private function scopes(): array
    {
        $scopes = ['openid', 'profile', 'email'];
        if (config('social_integrations.publishing_entitlements.linkedin', false)) {
            $scopes[] = 'w_member_social';
        }
        if (config('social_integrations.linkedin_organizations_enabled', false)) {
            $scopes[] = 'rw_organization_admin';
        }

        return $scopes;
    }

    public function authorizationUrl(string $state, string $redirectUri): string
    {
        return 'https://www.linkedin.com/oauth/v2/authorization?'.http_build_query([
            'response_type' => 'code',
            'client_id' => config('social_integrations.providers.linkedin.client_id'),
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'scope' => implode(' ', $this->scopes()),
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function exchange(string $code, string $redirectUri, ?string $state = null): OAuthGrant
    {
        $body = Http::asForm()->acceptJson()->timeout(15)->post('https://www.linkedin.com/oauth/v2/accessToken', [
            'grant_type' => 'authorization_code', 'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => config('social_integrations.providers.linkedin.client_id'),
            'client_secret' => config('social_integrations.providers.linkedin.client_secret'),
        ])->throw()->json();
        if (! is_string($body['access_token'] ?? null) || $body['access_token'] === '') {
            throw new RuntimeException('LinkedIn OAuth token exchange failed.');
        }
        $user = Http::withToken($body['access_token'])->acceptJson()->timeout(15)
            ->get('https://api.linkedin.com/v2/userinfo')->throw()->json();
        if (! is_string($user['sub'] ?? null) || $user['sub'] === '') {
            throw new RuntimeException('LinkedIn identity verification failed.');
        }

        return new OAuthGrant(
            $body['access_token'], $body['refresh_token'] ?? null, $user['sub'],
            isset($body['scope']) && is_string($body['scope'])
                ? array_values(array_filter(explode(' ', $body['scope']))) : $this->scopes(),
            (int) ($body['expires_in'] ?? 0),
            isset($body['refresh_token_expires_in']) ? (int) $body['refresh_token_expires_in'] : null,
        );
    }

    public function refresh(string $refreshToken): TokenRefreshResult
    {
        try {
            $response = Http::asForm()->acceptJson()->timeout(15)->post('https://www.linkedin.com/oauth/v2/accessToken', ['client_id' => config('social_integrations.providers.linkedin.client_id'), 'client_secret' => config('social_integrations.providers.linkedin.client_secret'), 'grant_type' => 'refresh_token', 'refresh_token' => $refreshToken]);

            return RefreshTokenResponse::parse($response, false, ' ');
        } catch (ConnectionException $exception) {
            throw new TokenRefreshFailure('PROVIDER_OUTCOME_UNCERTAIN');
        }
    }

    public function discover(OAuthGrant $grant): array
    {
        $user = Http::withToken($grant->accessToken)->acceptJson()->timeout(15)
            ->get('https://api.linkedin.com/v2/userinfo')->throw()->json();
        if (! is_string($user['sub'] ?? null) || $user['sub'] !== $grant->actorId) {
            throw new RuntimeException('LinkedIn account does not match the authorised identity.');
        }
        $accounts = [new DiscoveredSocialAccount($grant->actorId, 'MEMBER', (string) ($user['name'] ?? 'LinkedIn member'))];
        if (! config('social_integrations.linkedin_organizations_enabled', false)) {
            return $accounts;
        }
        // Organisation discovery is restricted to LinkedIn-approved Community Management products.
        $headers = [
            'Linkedin-Version' => (string) config('social_integrations.linkedin_api_version', '202610'),
            'X-Restli-Protocol-Version' => '2.0.0',
        ];
        $body = Http::withToken($grant->accessToken)->withHeaders($headers)->acceptJson()->timeout(15)
            ->get('https://api.linkedin.com/rest/organizationAcls', ['q' => 'roleAssignee', 'role' => 'ADMINISTRATOR'])
            ->throw()->json();
        if (! is_array($body['elements'] ?? null)) {
            throw new RuntimeException('Invalid LinkedIn organization ACL response.');
        }
        foreach (array_slice($body['elements'], 0, 50) as $acl) {
            if (($acl['role'] ?? '') !== 'ADMINISTRATOR') {
                continue;
            }
            $urn = $acl['organization'] ?? '';
            if (! is_string($urn) || ! preg_match('/^urn:li:organization:(\d+)$/', $urn, $matches)) {
                continue;
            }
            $org = Http::withToken($grant->accessToken)->withHeaders($headers)->acceptJson()->timeout(15)
                ->get('https://api.linkedin.com/rest/organizations/'.$matches[1])->throw()->json();
            $accounts[] = new DiscoveredSocialAccount($matches[1], 'ORGANIZATION',
                (string) ($org['localizedName'] ?? 'LinkedIn organization'),
                isset($org['vanityName']) ? (string) $org['vanityName'] : null);
        }

        return $accounts;
    }

    public function capabilities(OAuthGrant $grant, DiscoveredSocialAccount $account): array
    {
        return [
            'profile.read' => ['mode' => 'READ_ONLY', 'reason' => 'LinkedIn member or authorized organization discovered'],
            'post.publish' => ['mode' => 'UNAVAILABLE', 'reason' => 'LinkedIn publishing permissions and publishing service are not enabled yet'],
        ];
    }
}
