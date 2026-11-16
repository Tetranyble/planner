<?php

namespace App\Domain\Analytics\Provider;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Read-only X API v2. Fixed HTTPS origin; no redirects, writes, or HTTP retries. */
final class LaravelXPostStatsClient implements XPostStatsClientContract
{
    public function read(string $accessToken, string $postId): array
    {
        if (! XPostStatistics::snowflake($postId) || $accessToken === '' || strlen($accessToken) > 8192) {
            throw new \DomainException('Invalid X metrics request.');
        }
        try {
            $reply = Http::withToken($accessToken)->acceptJson()
                ->withOptions(['allow_redirects' => false, 'http_errors' => false])
                ->connectTimeout(5)->timeout(15)
                ->get('https://api.x.com/2/tweets/'.$postId, ['tweet.fields' => 'author_id,public_metrics']);
            $retry = $reply->header('Retry-After');

            return ['status' => $reply->status(),
                'payload' => $reply->successful() && is_array($reply->json()) ? $reply->json() : [],
                'retry_after' => is_string($retry) && preg_match('/^[0-9]{1,5}$/D', $retry)
                    ? min(3600, (int) $retry) : null];
        } catch (ConnectionException) {
            return ['status' => null, 'payload' => [], 'retry_after' => null];
        }
    }
}
