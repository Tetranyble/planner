<?php

namespace App\Social\Providers;

use App\Social\Contracts\SocialOAuthProvider;
use App\Social\Data\DiscoveredSocialAccount;
use App\Social\Data\OAuthGrant;
use Illuminate\Validation\ValidationException;

final class FacebookOAuthProvider implements SocialOAuthProvider
{
    public function __construct(private readonly MetaOAuthClient $meta) {}

    public function key(): string
    {
        return 'facebook';
    }

    private function scopes(): array
    {
        $scopes = ['pages_show_list', 'pages_read_engagement'];
        if (config('social_integrations.publishing_entitlements.facebook', false)) {
            $scopes[] = 'pages_manage_posts';
        }

        return $scopes;
    }

    public function authorizationUrl(string $state, string $redirectUri): string
    {
        return $this->meta->authorizationUrl($state, $redirectUri, $this->scopes());
    }

    public function exchange(string $code, string $redirectUri, ?string $state = null): OAuthGrant
    {
        return $this->meta->exchange($code, $redirectUri, $this->scopes());
    }

    public function discover(OAuthGrant $grant): array
    {
        $accounts = [];
        foreach ($this->meta->pages($grant) as $page) {
            $accounts[] = new DiscoveredSocialAccount($page['id'], 'PAGE', (string) ($page['name'] ?? 'Facebook Page'));
        }
        if ($accounts === []) {
            throw ValidationException::withMessages(['provider' => 'No Facebook Pages are available to this account.']);
        }

        return $accounts;
    }

    public function capabilities(OAuthGrant $grant, DiscoveredSocialAccount $account): array
    {
        return [
            'profile.read' => ['mode' => 'READ_ONLY', 'reason' => 'Facebook Page discovered through approved Page scopes'],
            'post.publish' => ['mode' => 'UNAVAILABLE', 'reason' => 'Page publishing service and pages_manage_posts consent are not enabled yet'],
        ];
    }
}
