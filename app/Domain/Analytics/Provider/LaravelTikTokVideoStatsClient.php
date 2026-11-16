<?php

namespace App\Domain\Analytics\Provider;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Fixed TikTok v2 creator-owned video query; HTTP POST has read-only provider semantics. */
final class LaravelTikTokVideoStatsClient implements TikTokVideoStatsClientContract
{
    public function read(string $accessToken, string $videoId): array
    {
        if (! TikTokVideoStatistics::validVideoId($videoId) || $accessToken === ''
            || strlen($accessToken) > 8192 || preg_match('/[\r\n\x00]/', $accessToken)) {
            throw new \DomainException('Invalid TikTok stats request.');
        }
        try {
            $response = Http::withToken($accessToken)->asJson()->acceptJson()
                ->withOptions(['allow_redirects' => false, 'http_errors' => false])
                ->connectTimeout(5)->timeout(15)
                ->post('https://open.tiktokapis.com/v2/video/query/?fields=id,view_count,like_count,comment_count',
                    ['filters' => ['video_ids' => [$videoId]]]);
            $raw = $response->successful() && is_array($response->json()) ? $response->json() : [];
            $status = $response->status();
            if ($status === 200 && ($raw['error']['code'] ?? null) !== 'ok') {
                $status = 422;
            }
            $retry = $response->header('Retry-After');

            return ['status' => $status, 'payload' => $status === 200 ? $raw : [],
                'retry_after' => is_string($retry) && preg_match('/^[0-9]{1,5}$/D', $retry)
                    ? min(3600, (int) $retry) : null];
        } catch (ConnectionException) {
            return ['status' => null, 'payload' => [], 'retry_after' => null];
        }
    }
}
