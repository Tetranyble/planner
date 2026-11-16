<?php

namespace App\Domain\Publishing\LiveContracts;

/** Provider-specific consent and actor rules. No endpoint or capability can override these. */
final class PublishingScopePolicy
{
    public static function required(string $provider, string $kind): array
    {
        return match (strtolower($provider)) {
            'facebook' => $kind === 'PAGE' ? ['pages_show_list', 'pages_read_engagement', 'pages_manage_posts'] : [],
            'linkedin' => $kind === 'MEMBER' ? ['w_member_social'] : [],
            'x' => $kind === 'CREATOR' ? ['users.read', 'tweet.write'] : [],
            default => [],
        };
    }

    public static function supports(string $provider, string $kind): bool
    {
        return self::required($provider, $kind) !== [];
    }

    public static function permitted(string $provider, string $kind, array $scopes): bool
    {
        $required = self::required($provider, $kind);

        return $required !== [] && array_diff($required, $scopes) === []
            && (bool) config('social_integrations.publishing_entitlements.'.strtolower($provider), false);
    }

    public static function authorRef(string $provider, string $kind, string $externalId): string
    {
        if ($provider === 'linkedin' && $kind === 'MEMBER') {
            if (! preg_match('/^[a-zA-Z0-9_-]{2,100}$/D', $externalId)) {
                throw new \DomainException('LinkedIn member author ID is invalid');
            }

            return 'urn:li:person:'.$externalId;
        }
        if ($provider === 'facebook' && $kind === 'PAGE') {
            return $externalId;
        }
        if ($provider === 'x' && $kind === 'CREATOR') {
            return $externalId;
        }
        throw new \DomainException('Provider account kind is not approved for direct text publishing');
    }
}
