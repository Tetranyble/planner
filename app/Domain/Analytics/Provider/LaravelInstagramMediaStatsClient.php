<?php

namespace App\Domain\Analytics\Provider;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Instagram business media insights. Owner identity must be returned explicitly. */
final class LaravelInstagramMediaStatsClient implements InstagramMediaStatsClientContract
{
    public function read(string $accessToken, string $mediaId, string $ownerId): array
    {
        if (! InstagramMediaStatistics::numericId($mediaId) || ! InstagramMediaStatistics::numericId($ownerId)
            || $accessToken === '' || strlen($accessToken) > 8192
            || preg_match('/[\r\n\x00]/', $accessToken)) {
            throw new \DomainException('Invalid Instagram insight request.');
        }
        $version = (string) config('social_integrations.meta_graph_version', 'v27.0');
        if (! preg_match('/^v[0-9]{1,2}\.[0-9]{1,2}$/D', $version)) {
            throw new \DomainException('Invalid Meta Graph version.');
        }
        $url = 'https://graph.facebook.com/'.$version.'/'.$mediaId;
        try {
            $identity = Http::withToken($accessToken)->acceptJson()
                ->withOptions(['allow_redirects' => false, 'http_errors' => false])
                ->connectTimeout(5)->timeout(15)
                ->get($url, ['fields' => 'id,owner,like_count,comments_count']);
            if (! $identity->successful()) {
                return self::failure($identity->status(), $identity->header('Retry-After'));
            }
            $body = $identity->json();
            if (! is_array($body) || ! hash_equals($mediaId, (string) ($body['id'] ?? ''))
                || ! hash_equals($ownerId, (string) ($body['owner']['id'] ?? ''))) {
                return ['status' => 422, 'payload' => [], 'retry_after' => null];
            }
            $insights = Http::withToken($accessToken)->acceptJson()
                ->withOptions(['allow_redirects' => false, 'http_errors' => false])
                ->connectTimeout(5)->timeout(15)
                ->get($url.'/insights', ['metric' => 'views']);
            if (! $insights->successful()) {
                return self::failure($insights->status(), $insights->header('Retry-After'));
            }

            return ['status' => 200, 'payload' => ['identity' => $body,
                'insights' => is_array($insights->json()) ? $insights->json() : []], 'retry_after' => null];
        } catch (ConnectionException) {
            return ['status' => null, 'payload' => [], 'retry_after' => null];
        }
    }

    private static function failure(int $status, mixed $retry): array
    {
        return ['status' => $status, 'payload' => [],
            'retry_after' => is_string($retry) && preg_match('/^[0-9]{1,5}$/D', $retry)
                ? min(3600, (int) $retry) : null];
    }
}
