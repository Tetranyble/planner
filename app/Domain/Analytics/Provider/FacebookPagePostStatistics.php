<?php

namespace App\Domain\Analytics\Provider;

/** Facebook Page post *media views* only; not X impressions, YouTube views, or unique reach. */
final class FacebookPagePostStatistics
{
    public static function validPostId(string $postId, string $pageId): bool
    {
        return (bool) preg_match('/^[1-9][0-9]{2,29}$/D', $pageId)
            && (bool) preg_match('/^'.preg_quote($pageId, '/').'_([1-9][0-9]{2,29})$/D', $postId);
    }

    /** @return array{post_id:string,account_id:string,views:int,likes:null,comments:null,sha256:string} */
    public static function normalize(array $payload, string $postId, string $pageId): array
    {
        if (! self::validPostId($postId, $pageId)) {
            throw new \DomainException('Invalid Facebook Page post identity.');
        }
        $identity = $payload['identity'] ?? null;
        $insights = $payload['insights']['data'] ?? null;
        if (! is_array($identity) || ! hash_equals($postId, (string) ($identity['id'] ?? ''))
            || ! is_array($identity['from'] ?? null)
            || ! hash_equals($pageId, (string) ($identity['from']['id'] ?? ''))
            || ! is_array($insights) || count($insights) !== 1) {
            throw new \DomainException('Facebook Page post identity or metric is missing or ambiguous.');
        }
        $metric = $insights[0];
        if (! is_array($metric) || ($metric['name'] ?? null) !== 'post_media_view'
            || ($metric['period'] ?? null) !== 'lifetime'
            || ! is_array($metric['values'] ?? null) || count($metric['values']) !== 1
            || ! is_array($metric['values'][0])) {
            throw new \DomainException('Unsupported Facebook Page post metric.');
        }
        $value = $metric['values'][0]['value'] ?? null;
        if (! is_int($value) && ! is_string($value)) {
            throw new \DomainException('Facebook Page post media view count unavailable.');
        }
        $value = (string) $value;
        if (! preg_match('/^(0|[1-9][0-9]{0,12})$/D', $value)
            || (float) $value > 1_000_000_000_000) {
            throw new \DomainException('Facebook Page metric outside supported range.');
        }
        $normalized = ['post_id' => $postId, 'account_id' => $pageId,
            'views' => (int) $value, 'likes' => null, 'comments' => null];
        $normalized['sha256'] = hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));

        return $normalized;
    }
}
