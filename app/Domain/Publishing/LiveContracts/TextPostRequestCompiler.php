<?php

namespace App\Domain\Publishing\LiveContracts;

/**
 * Strict, offline request compiler for three documented text-post endpoints.
 * Never executes an HTTP request and MUST NOT be used as dispatch authorization.
 * Actual capability, identity, role and application-review checks are separate gates.
 */
final class TextPostRequestCompiler
{
    public static function compile(string $provider, string $accountRef, string $text, string $linkedinVersion = '202606'): PreparedProviderRequest
    {
        if ($text === '' || trim($text) === '' || strlen($text) > 15000 || str_contains($text, "\0")) {
            throw new \InvalidArgumentException('Content missing or exceeds conservative request size');
        }
        $provider = strtolower($provider);

        return match ($provider) {
            'facebook' => self::facebook($accountRef, $text),
            'linkedin' => self::linkedin($accountRef, $text, $linkedinVersion),
            'x' => self::x($accountRef, $text),
            default => throw new \DomainException('Text post compiler not available for this provider'),
        };
    }

    private static function facebook(string $pageId, string $text): PreparedProviderRequest
    {
        if (! preg_match('/^[0-9]{3,30}$/D', $pageId)) {
            throw new \InvalidArgumentException('Invalid Facebook Page ID');
        }

        return new PreparedProviderRequest('facebook', 'POST', 'https://graph.facebook.com/v27.0/'.$pageId.'/feed',
            ['Content-Type' => 'application/json'], ['message' => $text], 'body.id');
    }

    private static function linkedin(string $author, string $text, string $version): PreparedProviderRequest
    {
        if (! preg_match('/^urn:li:(?:organization:[0-9]{2,30}|person:[a-zA-Z0-9_-]{2,100})$/D', $author)) {
            throw new \InvalidArgumentException('Invalid LinkedIn author URN');
        }
        if (! preg_match('/^20[0-9]{4}$/D', $version)) {
            throw new \InvalidArgumentException('Invalid LinkedIn API version');
        }

        return new PreparedProviderRequest('linkedin', 'POST', 'https://api.linkedin.com/rest/posts',
            ['Content-Type' => 'application/json', 'X-Restli-Protocol-Version' => '2.0.0', 'Linkedin-Version' => $version],
            ['author' => $author, 'commentary' => $text, 'visibility' => 'PUBLIC',
                'distribution' => ['feedDistribution' => 'MAIN_FEED', 'targetEntities' => [], 'thirdPartyDistributionChannels' => []],
                'lifecycleState' => 'PUBLISHED', 'isReshareDisabledByAuthor' => false], 'header.x-restli-id');
    }

    private static function x(string $actorId, string $text): PreparedProviderRequest
    {
        if (! preg_match('/^[0-9]{3,30}$/D', $actorId)) {
            throw new \InvalidArgumentException('Invalid X user ID');
        }

        // No actor ID in POST body: OAuth user context is verified by the dispatch gate.
        return new PreparedProviderRequest('x', 'POST', 'https://api.x.com/2/tweets',
            ['Content-Type' => 'application/json'], ['text' => $text], 'body.data.id');
    }
}
