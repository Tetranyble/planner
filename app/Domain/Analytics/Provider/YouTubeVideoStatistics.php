<?php

namespace App\Domain\Analytics\Provider;

/** Pure, fail-closed normalizer. No inflated zeroes for absent provider metrics. */
final class YouTubeVideoStatistics
{
    public static function normalize(array $payload, string $videoId, string $channelId): array
    {
        if (! preg_match('/^[A-Za-z0-9_-]{11}$/D', $videoId)
            || ! preg_match('/^UC[A-Za-z0-9_-]{22}$/D', $channelId)) {
            throw new \DomainException('Expected YouTube video/channel reference is invalid.');
        }
        if (! isset($payload['items']) || ! is_array($payload['items']) || count($payload['items']) !== 1) {
            throw new \DomainException('YouTube video response is missing or ambiguous.');
        }
        $item = $payload['items'][0];
        if (! is_array($item) || ! hash_equals($videoId, (string) ($item['id'] ?? ''))
            || ! hash_equals($channelId, (string) ($item['snippet']['channelId'] ?? ''))
            || ! isset($item['statistics']) || ! is_array($item['statistics'])) {
            throw new \DomainException('YouTube video ownership or response format does not match.');
        }
        $stats = $item['statistics'];
        $normalized = ['video_id' => $videoId, 'channel_id' => $channelId,
            'views' => self::count($stats, 'viewCount'),
            'likes' => self::count($stats, 'likeCount'),
            'comments' => self::count($stats, 'commentCount')];
        if ($normalized['views'] === null) {
            throw new \DomainException('YouTube viewCount is missing.');
        }
        $normalized['sha256'] = hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));

        return $normalized;
    }

    private static function count(array $stats, string $name): ?int
    {
        if (! array_key_exists($name, $stats)) {
            return null;
        }
        $v = $stats[$name];
        if (! is_string($v) && ! is_int($v)) {
            throw new \DomainException('Malformed YouTube statistics.');
        }
        $v = (string) $v;
        if (! preg_match('/^(0|[1-9][0-9]{0,12})$/D', $v)
            || (float) $v > 1_000_000_000_000) {
            throw new \DomainException('YouTube count outside supported range.');
        }

        return (int) $v;
    }
}
