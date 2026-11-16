<?php

namespace App\Social\Providers;

use App\Social\Contracts\SocialOAuthProvider;
use App\Social\Data\DiscoveredSocialAccount;
use App\Social\Data\OAuthGrant;
use Illuminate\Validation\ValidationException;

/** Uses Instagram API with Facebook Login: professional account linked to a Page. */
final class InstagramOAuthProvider implements SocialOAuthProvider
{
    public function __construct(private readonly MetaOAuthClient $meta) {}

    public function key(): string
    {
        return 'instagram';
    }

    public function authorizationUrl(string $state, string $redirectUri): string
    {
        return $this->meta->authorizationUrl($state, $redirectUri, [
            'pages_show_list', 'pages_read_engagement', 'instagram_basic',
        ]);
    }

    public function exchange(string $code, string $redirectUri, ?string $state = null): OAuthGrant
    {
        return $this->meta->exchange($code, $redirectUri, [
            'pages_show_list', 'pages_read_engagement', 'instagram_basic',
        ]);
    }

    public function discover(OAuthGrant $grant): array
    {
        $accounts = [];
        foreach ($this->meta->pages($grant, true) as $page) {
            $ig = $page['instagram_business_account'] ?? null;
            if (! is_array($ig) || ! is_string($ig['id'] ?? null) || $ig['id'] === '') {
                continue;
            }
            $accounts[$ig['id']] = new DiscoveredSocialAccount(
                $ig['id'], 'PROFESSIONAL',
                (string) ($ig['name'] ?? $ig['username'] ?? 'Instagram professional account'),
                isset($ig['username']) ? (string) $ig['username'] : null,
            );
        }
        if ($accounts === []) {
            throw ValidationException::withMessages([
                'provider' => 'No eligible Instagram professional account is linked to the accessible Facebook Pages.',
            ]);
        }

        return array_values($accounts);
    }

    public function capabilities(OAuthGrant $grant, DiscoveredSocialAccount $account): array
    {
        return [
            'profile.read' => ['mode' => 'READ_ONLY', 'reason' => 'Linked Instagram professional account discovered'],
            'media.publish' => ['mode' => 'UNAVAILABLE', 'reason' => 'instagram_content_publish and media publishing are not enabled yet'],
        ];
    }
}
