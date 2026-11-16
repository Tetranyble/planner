<?php

namespace App\Domain\Analytics\Provider;

/** Creator-scoped TikTok Content Display API video statistics, not ad attribution. */
final class TikTokVideoStatistics
{
    public static function validVideoId(string $videoId): bool
    {
        return (bool) preg_match('/^[1-9][0-9]{5,19}$/D', $videoId);
    }

    public static function normalize(array $payload, string $videoId, string $accountId): array
    {
        if (! self::validVideoId($videoId) || $accountId === '' || strlen($accountId) > 160) {
            throw new \DomainException('TikTok video/account reference is invalid.');
        }
        $videos = $payload['data']['videos'] ?? null;
        if (($payload['error']['code'] ?? null) !== 'ok'
            || ! is_array($videos) || count($videos) !== 1 || ! is_array($videos[0])
            || ! hash_equals($videoId, (string) ($videos[0]['id'] ?? ''))) {
            throw new \DomainException('TikTok video absent, unauthorized or ambiguous.');
        }
        $item = $videos[0];
        $result = ['video_id' => $videoId, 'account_id' => $accountId,
            'views' => self::count($item, 'view_count'),
            'likes' => self::count($item, 'like_count'),
            'comments' => self::count($item, 'comment_count')];
        if ($result['views'] === null) {
            throw new \DomainException('TikTok did not provide a video view count.');
        }
        $result['sha256'] = hash('sha256', json_encode($result, JSON_THROW_ON_ERROR));

        return $result;
    }

    private static function count(array $payload, string $field): ?int
    {
        $count = $payload[$field] ?? null;
        if ($count === null) {
            return null;
        }
        if (! is_int($count) && ! is_string($count)) {
            throw new \DomainException('TikTok metric has an invalid type.');
        }
        $count = (string) $count;
        if (! preg_match('/^(0|[1-9][0-9]{0,12})$/D', $count)
            || (float) $count > 1_000_000_000_000) {
            throw new \DomainException('TikTok metric exceeds supported range.');
        }

        return (int) $count;
    }
}
