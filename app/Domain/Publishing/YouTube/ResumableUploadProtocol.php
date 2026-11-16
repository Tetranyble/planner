<?php

namespace App\Domain\Publishing\YouTube;

/**
 * Strict, provider-specific resumable upload framing. No network or file access.
 * After ANY ambiguous transfer, query Google's committed byte range; never guess.
 */
final class ResumableUploadProtocol
{
    public const CHUNK_BYTES = 8 * 1024 * 1024; // 32 x 256 KiB

    public const MAX_MEDIA_BYTES = 20 * 1024 * 1024 * 1024;

    public static function assertSessionUrl(string $url): void
    {
        if (strlen($url) > 2048 || preg_match('/[\x00-\x20\\\\]/', $url)) {
            throw new \DomainException('Invalid Google upload session URL.');
        }
        $parts = parse_url($url);
        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'https'
            || ($parts['host'] ?? null) !== 'www.googleapis.com'
            || ($parts['path'] ?? null) !== '/upload/youtube/v3/videos'
            || isset($parts['port'],$parts['user']) || isset($parts['fragment'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            throw new \DomainException('Google upload session origin is not permitted.');
        }
        $query = $parts['query'] ?? '';
        if ($query === '' || preg_match('/(?:^|&)(?:[^=&]*%|[^=&]*\[)/i', $query)) {
            throw new \DomainException('Malformed Google upload session query.');
        }
        $segments = explode('&', $query);
        $params = [];
        foreach ($segments as $segment) {
            $pair = explode('=', $segment, 2);
            if (count($pair) !== 2 || isset($params[$pair[0]]) || ! in_array($pair[0], ['uploadType', 'upload_id', 'part'], true)) {
                throw new \DomainException('Unrecognized or duplicate upload session parameter.');
            }
            $params[$pair[0]] = rawurldecode($pair[1]);
        }
        if (($params['uploadType'] ?? null) !== 'resumable'
            || ! in_array($params['part'] ?? null, ['snippet,status', 'status,snippet'], true)
            || ! preg_match('/^[A-Za-z0-9_~-]{8,256}$/D', $params['upload_id'] ?? '')) {
            throw new \DomainException('Google upload session identity is invalid.');
        }
    }

    public static function assertMedia(int $bytes, string $mime): void
    {
        if ($bytes < 1 || $bytes > self::MAX_MEDIA_BYTES || ! in_array(strtolower($mime), [
            'video/mp4', 'video/quicktime', 'video/webm', 'video/x-matroska', 'video/avi', 'video/x-msvideo',
        ], true)) {
            throw new \DomainException('Unsupported or unbounded YouTube media asset.');
        }
    }

    public static function initHeaders(int $total, string $mime): array
    {
        self::assertMedia($total, $mime);

        return ['X-Upload-Content-Type' => $mime, 'X-Upload-Content-Length' => (string) $total,
            'Content-Type' => 'application/json; charset=UTF-8'];
    }

    public static function probeHeaders(int $total): array
    {
        if ($total < 1 || $total > self::MAX_MEDIA_BYTES) {
            throw new \DomainException('Invalid media size.');
        }

        return ['Content-Length' => '0', 'Content-Range' => 'bytes */'.$total];
    }

    public static function chunk(int $offset, int $total): array
    {
        if ($total < 1 || $total > self::MAX_MEDIA_BYTES || $offset < 0 || $offset >= $total) {
            throw new \DomainException('Invalid resumable byte range.');
        }
        $length = min(self::CHUNK_BYTES, $total - $offset);

        return ['start' => $offset, 'length' => $length, 'end' => $offset + $length - 1,
            'headers' => ['Content-Length' => (string) $length,
                'Content-Range' => "bytes {$offset}-".($offset + $length - 1)."/{$total}"]];
    }

    /** Returns offset only when Google positively acknowledges a range. */
    public static function acknowledge(int $status, ?string $range, ?array $json, int $total): array
    {
        if ($status === 308) {
            if ($range === null || trim($range) === '') {
                return ['state' => 'OFFSET_CONFIRMED', 'offset' => 0];
            }
            if (! preg_match('/^bytes=0-([0-9]+)$/D', trim($range), $m)) {
                return ['state' => 'UNCERTAIN', 'code' => 'INVALID_GOOGLE_RANGE'];
            }
            $end = filter_var($m[1], FILTER_VALIDATE_INT);
            if ($end === false || $end < 0 || $end >= $total) {
                return ['state' => 'UNCERTAIN', 'code' => 'GOOGLE_OFFSET_OUT_OF_BOUNDS'];
            }

            return ['state' => 'OFFSET_CONFIRMED', 'offset' => $end + 1];
        }
        if (in_array($status, [200, 201], true)) {
            $id = $json['id'] ?? null;
            if (! is_string($id) || ! preg_match('/^[A-Za-z0-9_-]{6,64}$/D', $id)) {
                return ['state' => 'UNCERTAIN', 'code' => 'YOUTUBE_VIDEO_ID_MISSING'];
            }

            return ['state' => 'UPLOADED', 'video_id' => $id];
        }
        if (in_array($status, [404, 410], true)) {
            return ['state' => 'MANUAL_REQUIRED', 'code' => 'YOUTUBE_SESSION_EXPIRED'];
        }
        if ($status === 401 || $status === 403) {
            return ['state' => 'MANUAL_REQUIRED', 'code' => 'YOUTUBE_AUTH_REJECTED'];
        }
        if ($status === 429 || $status >= 500) {
            return ['state' => 'NEEDS_PROBE', 'code' => 'YOUTUBE_RESPONSE_AMBIGUOUS'];
        }

        return ['state' => 'UNCERTAIN', 'code' => 'YOUTUBE_RESPONSE_UNRECOGNIZED'];
    }

    public static function verifiedVideo(string $expectedVideoId, string $expectedChannelId, array $body): array
    {
        $items = $body['items'] ?? null;
        if (! is_array($items) || count($items) !== 1) {
            return ['state' => 'UNCERTAIN', 'code' => 'YOUTUBE_VIDEO_NOT_UNIQUE'];
        }
        $item = $items[0];
        if (! is_array($item) || ! hash_equals($expectedVideoId, (string) ($item['id'] ?? ''))
            || ! hash_equals($expectedChannelId, (string) ($item['snippet']['channelId'] ?? ''))) {
            return ['state' => 'UNCERTAIN', 'code' => 'YOUTUBE_CHANNEL_OR_VIDEO_MISMATCH'];
        }
        $upload = $item['status']['uploadStatus'] ?? null;
        $processing = $item['processingDetails']['processingStatus'] ?? null;
        if (in_array($upload, ['failed', 'rejected', 'deleted'], true) || $processing === 'failed') {
            return ['state' => 'FAILED', 'code' => 'YOUTUBE_VIDEO_PROCESSING_FAILED'];
        }
        if ($processing === 'succeeded' && in_array($upload, ['uploaded', 'processed'], true)) {
            return ['state' => 'VERIFIED', 'video_id' => $expectedVideoId, 'owner_id' => $expectedChannelId];
        }
        if (in_array($upload, ['uploaded', 'processed'], true) && in_array($processing, [null, 'processing'], true)) {
            return ['state' => 'PROCESSING', 'code' => 'YOUTUBE_STILL_PROCESSING'];
        }

        return ['state' => 'UNCERTAIN', 'code' => 'YOUTUBE_PROCESSING_STATUS_UNKNOWN'];
    }
}
