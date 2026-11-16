<?php

namespace App\Domain\Publishing\Delivery;

/** Internal synthetic schema, deliberately NOT a real network provider API schema. */
final class MockProviderRequestCompiler
{
    private const RULES = [
        'facebook' => ['TEXT', 'IMAGE', 'VIDEO'],
        'instagram' => ['IMAGE', 'VIDEO'],
        'linkedin' => ['TEXT', 'IMAGE', 'VIDEO'],
        'x' => ['TEXT', 'IMAGE', 'VIDEO'],
        'tiktok' => ['VIDEO'],
        'youtube' => ['VIDEO'],
    ];

    public static function compile(DispatchEnvelope $envelope): MockHttpExchange
    {
        $provider = strtolower($envelope->provider);
        if (! isset(self::RULES[$provider])) {
            throw new \DomainException('Unsupported provider');
        }
        if (! in_array($envelope->mediaKind, self::RULES[$provider], true)) {
            throw new \DomainException('Unsupported mock media kind');
        }

        // Do not include content text, URLs, user IDs, real media or secrets.
        return new MockHttpExchange($provider, 'POST', '/mock/v1/'.$provider.'/publish', [
            'Content-Type' => 'application/json',
            'X-Mock-Operation-Key' => $envelope->operationKey,
        ], [
            'provider' => $provider,
            'operation_key' => $envelope->operationKey,
            'media_kind' => $envelope->mediaKind,
            'digest' => $envelope->contentDigest,
            'target_alias' => 'MOCK_TARGET',
        ]);
    }
}
