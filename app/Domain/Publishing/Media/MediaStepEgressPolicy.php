<?php

namespace App\Domain\Publishing\Media;

/** Strict endpoint pinning for future staged media workers. No arbitrary URLs or redirects. */
final class MediaStepEgressPolicy
{
    public static function assertAllowed(MediaStepRequest $request): void
    {
        $url = parse_url($request->url);
        if (! is_array($url) || ($url['scheme'] ?? '') !== 'https'
            || isset($url['user']) || isset($url['pass']) || isset($url['port'])
            || isset($url['fragment'])) {
            throw new \DomainException('Media provider endpoint must use pinned HTTPS without credentials or redirects.');
        }
        $host = $url['host'] ?? '';
        $path = $url['path'] ?? '';
        $query = $url['query'] ?? '';
        $approvedQuery = ($request->provider === 'instagram' && $request->operation === 'CHECK_CONTAINER_STATUS')
            ? 'fields=status_code' : (($request->provider === 'instagram' && $request->operation === 'VERIFY_MEDIA_RECEIPT')
            ? 'fields=id%2Cowner' : (($request->provider === 'youtube' && $request->operation === 'INITIALIZE_UPLOAD_SESSION')
            ? 'uploadType=resumable&part=snippet%2Cstatus' : ''));
        if ($query !== $approvedQuery) {
            throw new \DomainException('Unexpected provider query parameters.');
        }
        $valid = match ($request->provider) {
            'instagram' => $host === 'graph.facebook.com' && (
                ($request->operation === 'CREATE_CONTAINER' && $request->method === 'POST'
                    && (bool) preg_match('~^/v[0-9]+\\.[0-9]+/[0-9]{3,30}/media$~D', $path))
                || ($request->operation === 'PUBLISH_CONTAINER' && $request->method === 'POST'
                    && (bool) preg_match('~^/v[0-9]+\\.[0-9]+/[0-9]{3,30}/media_publish$~D', $path))
                || ($request->operation === 'VERIFY_MEDIA_RECEIPT' && $request->method === 'GET'
                    && (bool) preg_match('~^/v[0-9]+\\.[0-9]+/[0-9]{3,30}$~D', $path))
                || ($request->operation === 'CHECK_CONTAINER_STATUS' && $request->method === 'GET'
                    && (bool) preg_match('~^/v[0-9]+\\.[0-9]+/[0-9]{3,30}$~D', $path))),
            'tiktok' => $host === 'open.tiktokapis.com' && $request->method === 'POST' && (
                ($request->operation === 'INITIALIZE_VIDEO' && $path === '/v2/post/publish/video/init/')
                || (in_array($request->operation, ['FETCH_PUBLISH_STATUS', 'VERIFY_VIDEO_RECEIPT'], true)
                    && $path === '/v2/post/publish/status/fetch/')),
            'youtube' => $host === 'www.googleapis.com' && $request->method === 'POST'
                && $request->operation === 'INITIALIZE_UPLOAD_SESSION'
                && $path === '/upload/youtube/v3/videos',
            default => false,
        };
        if (! $valid) {
            throw new \DomainException('Unsupported provider media endpoint or operation.');
        }
        foreach ($request->headers as $name => $value) {
            if (! is_string($name) || ! is_string($value) || preg_match('/[\r\n]/', $name.$value)
                || in_array(strtolower($name), ['authorization', 'cookie', 'host', 'proxy-authorization'], true)) {
                throw new \DomainException('Provider secret or unsafe header in media request specification.');
            }
        }
    }
}
