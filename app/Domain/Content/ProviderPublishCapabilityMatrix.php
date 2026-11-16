<?php

namespace App\Domain\Content;

/** Names must match the six OAuth adapters' existing capability catalogue.
 * These are lookup keys, not approval of network publishing.
 */
final class ProviderPublishCapabilityMatrix
{
    private const CAPABILITIES = [
        'facebook' => 'post.publish',
        'instagram' => 'media.publish',
        'linkedin' => 'post.publish',
        'x' => 'post.publish',
        'tiktok' => 'video.publish',
        'youtube' => 'video.publish',
    ];

    public static function capabilityFor(string $provider): ?string
    {
        return self::CAPABILITIES[strtolower($provider)] ?? null;
    }

    public static function providers(): array
    {
        return self::CAPABILITIES;
    }
}
