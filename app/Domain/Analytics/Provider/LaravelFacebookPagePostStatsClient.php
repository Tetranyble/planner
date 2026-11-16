<?php

namespace App\Domain\Analytics\Provider;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Meta Graph API read-only: independent post ownership check followed by current media-view metric. */
final class LaravelFacebookPagePostStatsClient implements FacebookPagePostStatsClientContract
{
    public function read(string $pageToken, string $postId, string $pageId): array
    {
        if (! FacebookPagePostStatistics::validPostId($postId, $pageId)
            || $pageToken === '' || strlen($pageToken) > 8192
            || preg_match('/[\r\n\x00]/', $pageToken)) {
            throw new \DomainException('Invalid Facebook Page metrics request.');
        }
        $version = (string) config('social_integrations.meta_graph_version', 'v27.0');
        if (! preg_match('/^v[0-9]{1,2}\.[0-9]{1,2}$/D', $version)) {
            throw new \DomainException('Invalid Meta Graph version.');
        }
        $url = 'https://graph.facebook.com/'.$version.'/'.$postId;
        try {
            $identity = Http::withToken($pageToken)->acceptJson()
                ->withOptions(['allow_redirects' => false, 'http_errors' => false])
                ->connectTimeout(5)->timeout(15)->get($url, ['fields' => 'id,from{id}']);
            if (! $identity->successful()) {
                return self::failure($identity->status(), $identity->header('Retry-After'));
            }
            $body = $identity->json();
            if (! is_array($body) || ! hash_equals($postId, (string) ($body['id'] ?? ''))
                || ! hash_equals($pageId, (string) ($body['from']['id'] ?? ''))) {
                return ['status' => 422, 'payload' => [], 'retry_after' => null];
            }
            $metrics = Http::withToken($pageToken)->acceptJson()
                ->withOptions(['allow_redirects' => false, 'http_errors' => false])
                ->connectTimeout(5)->timeout(15)
                ->get($url.'/insights', ['metric' => 'post_media_view']);
            if (! $metrics->successful()) {
                return self::failure($metrics->status(), $metrics->header('Retry-After'));
            }

            return ['status' => 200, 'payload' => ['identity' => $body,
                'insights' => is_array($metrics->json()) ? $metrics->json() : []],
                'retry_after' => null];
        } catch (ConnectionException) {
            return ['status' => null, 'payload' => [], 'retry_after' => null];
        }
    }

    private static function failure(int $status, mixed $retryAfter): array
    {
        return ['status' => $status, 'payload' => [],
            'retry_after' => is_string($retryAfter) && preg_match('/^[0-9]{1,5}$/D', $retryAfter)
                ? min(3600, (int) $retryAfter) : null];
    }
}
