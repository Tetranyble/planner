<?php

namespace App\Domain\Analytics\Provider;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Fixed Google API host and params; does not follow redirects or expose token in output. */
final class LaravelYouTubeVideoStatsClient implements YouTubeVideoStatsClientContract
{
    public function read(string $accessToken, string $videoId): array
    {
        if (! preg_match('/^[A-Za-z0-9_-]{11}$/D', $videoId) || $accessToken === '' || strlen($accessToken) > 8192) {
            throw new \DomainException('Invalid YouTube analytics request.');
        }
        try {
            $reply = Http::withToken($accessToken)
                ->acceptJson()->withOptions(['allow_redirects' => false, 'http_errors' => false])
                ->connectTimeout(5)->timeout(15)
                ->get('https://www.googleapis.com/youtube/v3/videos', [
                    'part' => 'snippet,statistics', 'id' => $videoId, 'fields' => 'items(id,snippet(channelId),statistics(viewCount,likeCount,commentCount))',
                ]);
            $retry = $reply->header('Retry-After');

            return ['status' => $reply->status(),
                // Never persist raw provider payloads or request metadata.
                'payload' => $reply->successful() && is_array($reply->json()) ? $reply->json() : [],
                'retry_after' => is_string($retry) && preg_match('/^[0-9]{1,5}$/D', $retry)
                    ? min(3600, (int) $retry) : null];
        } catch (ConnectionException) {
            // Read-only request: a transport failure can safely defer, without any mutation.
            return ['status' => null, 'payload' => [], 'retry_after' => null];
        }
    }
}
