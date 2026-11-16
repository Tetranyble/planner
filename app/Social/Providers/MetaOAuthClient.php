<?php

namespace App\Social\Providers;

use App\Social\Data\OAuthGrant;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Meta Graph API transport shared by Facebook Pages and linked Instagram professional accounts. */
final class MetaOAuthClient
{
    private function version(): string
    {
        return (string) config('social_integrations.meta_graph_version', 'v27.0');
    }

    public function authorizationUrl(string $state, string $redirectUri, array $scopes): string
    {
        return 'https://www.facebook.com/'.$this->version().'/dialog/oauth?'.http_build_query([
            'client_id' => config('social_integrations.providers.facebook.client_id'),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(',', $scopes),
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function exchange(string $code, string $redirectUri, array $scopes): OAuthGrant
    {
        $body = Http::acceptJson()->timeout(15)->get('https://graph.facebook.com/'.$this->version().'/oauth/access_token', [
            'client_id' => config('social_integrations.providers.facebook.client_id'),
            'client_secret' => config('social_integrations.providers.facebook.client_secret'),
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ])->throw()->json();
        if (! is_string($body['access_token'] ?? null) || $body['access_token'] === '') {
            throw new RuntimeException('Meta OAuth token exchange did not return an access token.');
        }
        $token = $body['access_token'];
        $actor = Http::withToken($token)->acceptJson()->timeout(15)
            ->get('https://graph.facebook.com/'.$this->version().'/me', ['fields' => 'id'])
            ->throw()->json();
        if (! is_string($actor['id'] ?? null) || $actor['id'] === '') {
            throw new RuntimeException('Meta authenticated identity could not be verified.');
        }
        $permissionResponse = Http::withToken($token)->acceptJson()->timeout(15)
            ->get('https://graph.facebook.com/'.$this->version().'/me/permissions')->throw()->json();
        if (! is_array($permissionResponse['data'] ?? null)) {
            throw new RuntimeException('Meta did not return a verifiable permissions response.');
        }
        $granted = [];
        foreach ($permissionResponse['data'] as $entry) {
            if (is_array($entry) && ($entry['status'] ?? '') === 'granted'
                && is_string($entry['permission'] ?? null)) {
                $granted[] = $entry['permission'];
            }
        }
        if (array_diff($scopes, $granted) !== []) {
            throw new RuntimeException('Meta permissions are incomplete. Reauthorize with all required permissions.');
        }

        // Meta user/Page tokens do not expose an OAuth refresh_token. Expiration is provider-controlled.
        return new OAuthGrant($token, null, $actor['id'], $granted, (int) ($body['expires_in'] ?? 0));
    }

    /** @return list<array<string,mixed>> */
    public function pages(OAuthGrant $grant, bool $includeInstagram = false): array
    {
        $pages = [];
        $after = null;
        $seen = [];
        for ($i = 0; $i < 10; $i++) {
            $query = [
                'fields' => $includeInstagram ? 'id,name,instagram_business_account{id,username,name}' : 'id,name',
                'limit' => 100,
            ];
            if ($after !== null) {
                $query['after'] = $after;
            }
            $body = Http::withToken($grant->accessToken)->acceptJson()->timeout(15)
                ->get('https://graph.facebook.com/'.$this->version().'/me/accounts', $query)
                ->throw()->json();
            if (! is_array($body['data'] ?? null)) {
                throw new RuntimeException('Meta returned an invalid Pages response.');
            }
            foreach ($body['data'] as $page) {
                if (! is_array($page) || ! is_string($page['id'] ?? null) || $page['id'] === '') {
                    continue;
                }
                $pages[$page['id']] = $page;
            }
            $cursor = $body['paging']['cursors']['after'] ?? null;
            // Do not fetch the provider-supplied next URL: keep all requests on the fixed Graph host.
            if ($i === 9 && ! empty($body['paging']['next'])) {
                throw new RuntimeException('Meta returned more Pages than the supported discovery limit.');
            }
            if (empty($body['paging']['next']) || ! is_string($cursor) || isset($seen[$cursor])) {
                break;
            }
            $seen[$cursor] = true;
            $after = $cursor;
        }

        return array_values($pages);
    }
}
