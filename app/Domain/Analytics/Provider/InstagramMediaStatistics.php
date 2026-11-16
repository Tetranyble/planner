<?php

namespace App\Domain\Analytics\Provider;

/** Instagram media views, not unique reach, YouTube views or Facebook crosspost views. */
final class InstagramMediaStatistics
{
    public static function numericId(string $id): bool
    {
        return (bool) preg_match('/^[1-9][0-9]{5,29}$/D', $id);
    }

    public static function normalize(array $payload, string $mediaId, string $ownerId): array
    {
        if (! self::numericId($mediaId) || ! self::numericId($ownerId)) {
            throw new \DomainException('Invalid Instagram media identity.');
        }
        $identity = $payload['identity'] ?? null;
        $metrics = $payload['insights']['data'] ?? null;
        if (! is_array($identity) || ! hash_equals($mediaId, (string) ($identity['id'] ?? ''))
            || ! is_array($identity['owner'] ?? null)
            || ! hash_equals($ownerId, (string) ($identity['owner']['id'] ?? ''))
            || ! is_array($metrics) || count($metrics) !== 1 || ! is_array($metrics[0])
            || ($metrics[0]['name'] ?? '') !== 'views') {
            throw new \DomainException('Instagram ownership or insight identity mismatch.');
        }
        $value = null;
        if (isset($metrics[0]['values']) && is_array($metrics[0]['values'])
            && count($metrics[0]['values']) === 1 && is_array($metrics[0]['values'][0])) {
            $value = $metrics[0]['values'][0]['value'] ?? null;
        } elseif (isset($metrics[0]['total_value']) && is_array($metrics[0]['total_value'])) {
            $value = $metrics[0]['total_value']['value'] ?? null;
        }
        $result = ['post_id' => $mediaId, 'account_id' => $ownerId,
            'views' => self::count($value),
            'likes' => self::count($identity['like_count'] ?? null, true),
            'comments' => self::count($identity['comments_count'] ?? null, true)];
        if ($result['views'] === null) {
            throw new \DomainException('Instagram media views were unavailable.');
        }
        $result['sha256'] = hash('sha256', json_encode($result, JSON_THROW_ON_ERROR));

        return $result;
    }

    private static function count(mixed $value, bool $optional = false): ?int
    {
        if ($value === null && $optional) {
            return null;
        }
        if (! is_int($value) && ! is_string($value)) {
            throw new \DomainException('Instagram metric type unavailable or invalid.');
        }
        $value = (string) $value;
        if (! preg_match('/^(0|[1-9][0-9]{0,12})$/D', $value)
            || (float) $value > 1_000_000_000_000) {
            throw new \DomainException('Instagram metric outside supported range.');
        }

        return (int) $value;
    }
}
