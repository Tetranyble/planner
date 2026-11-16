<?php

namespace App\Domain\Analytics\Provider;

/** Pure X public-metrics normalizer. Unknown values are null, never fabricated zeros. */
final class XPostStatistics
{
    public static function normalize(array $payload, string $postId, string $accountId): array
    {
        if (! self::snowflake($postId) || ! self::snowflake($accountId)) {
            throw new \DomainException('Invalid expected X identifiers.');
        }
        $post = $payload['data'] ?? null;
        if (! is_array($post) || ! hash_equals($postId, (string) ($post['id'] ?? ''))
            || ! hash_equals($accountId, (string) ($post['author_id'] ?? ''))
            || ! is_array($post['public_metrics'] ?? null)) {
            throw new \DomainException('X post/author mismatch or missing public metrics.');
        }
        $counts = $post['public_metrics'];
        $normalized = ['post_id' => $postId, 'account_id' => $accountId,
            // Public impression_count is NOT equivalent to unique reach or paid impressions.
            'views' => self::count($counts, 'impression_count'),
            'likes' => self::count($counts, 'like_count'),
            'comments' => self::count($counts, 'reply_count')];
        if ($normalized['views'] === null && $normalized['likes'] === null && $normalized['comments'] === null) {
            throw new \DomainException('X returned no supported public metrics.');
        }
        $normalized['sha256'] = hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));

        return $normalized;
    }

    public static function snowflake(string $id): bool
    {
        return (bool) preg_match('/^[1-9][0-9]{0,19}$/D', $id);
    }

    private static function count(array $values, string $name): ?int
    {
        if (! array_key_exists($name, $values) || $values[$name] === null) {
            return null;
        }
        $value = $values[$name];
        if (! is_int($value) && ! is_string($value)) {
            throw new \DomainException('Invalid X metric type.');
        }
        $value = (string) $value;
        if (! preg_match('/^(0|[1-9][0-9]{0,12})$/D', $value) || (float) $value > 1_000_000_000_000) {
            throw new \DomainException('X metric outside supported range.');
        }

        return (int) $value;
    }
}
