<?php

namespace App\Domain\Content;

/** Deterministic conservative media editorial screening; not an API upload approval. */
final class PlatformMediaPreflightRules
{
    /** @param list<array{mime_type?:string,byte_size?:int,width?:int,height?:int,duration_ms?:int}> $media */
    public static function inspect(string $provider, array $media): array
    {
        $provider = strtolower(trim($provider));
        $issues = [];
        if (! in_array($provider, PlatformVariantRules::PROVIDERS, true)) {
            $issues[] = 'UNKNOWN_PLATFORM';
        }
        $count = count($media);
        $requiresMedia = in_array($provider, ['instagram', 'tiktok', 'youtube'], true);
        if ($requiresMedia && $count === 0) {
            $issues[] = 'MEDIA_REQUIRED';
        }
        if ($count > (match ($provider) {
            'instagram' => 10,'facebook' => 10,'linkedin' => 9,'x' => 4,'tiktok' => 1,'youtube' => 1,default => 0
        })) {
            $issues[] = 'MEDIA_COUNT_LIMIT';
        }
        $hasVideo = false;
        $hasImage = false;
        foreach ($media as $item) {
            $mime = strtolower((string) ($item['mime_type'] ?? ''));
            $bytes = (int) ($item['byte_size'] ?? 0);
            $isImage = in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true);
            $isVideo = in_array($mime, ['video/mp4', 'video/quicktime', 'video/webm'], true);
            if ($isImage) {
                $hasImage = true;
            }
            if ($isVideo) {
                $hasVideo = true;
            }
            if (! $isImage && ! $isVideo) {
                $issues[] = 'MEDIA_TYPE_UNSUPPORTED';

                continue;
            }
            if ($bytes <= 0 || $bytes > ($isImage ? 20_000_000 : 2_000_000_000)) {
                $issues[] = 'MEDIA_SIZE_UNVERIFIED';
            }
            if ($isImage && $provider === 'youtube') {
                $issues[] = 'VIDEO_REQUIRED';
            }
            if ($isImage && $provider === 'tiktok') {
                $issues[] = 'TIKTOK_VIDEO_ONLY_IN_THIS_WORKFLOW';
            }
            if ($provider === 'instagram' && $isImage && $mime !== 'image/jpeg') {
                $issues[] = 'INSTAGRAM_IMAGE_JPEG_REQUIRED';
            }
            if ($isVideo && $provider === 'instagram' && $mime !== 'video/mp4') {
                $issues[] = 'INSTAGRAM_MP4_REQUIRED';
            }
            if ($isVideo && $provider === 'tiktok' && $mime === 'video/webm') {
                $issues[] = 'TIKTOK_VIDEO_FORMAT_UNSUPPORTED';
            }
            if ($isImage && ((int) ($item['width'] ?? 0) < 1 || (int) ($item['height'] ?? 0) < 1)) {
                $issues[] = 'IMAGE_DIMENSIONS_MISSING';
            }
            if ($isVideo && (int) ($item['duration_ms'] ?? 0) < 1) {
                $issues[] = 'VIDEO_DURATION_MISSING';
            }
        }
        if ($hasVideo && $hasImage && in_array($provider, ['instagram', 'tiktok', 'youtube'], true)) {
            $issues[] = 'MIXED_MEDIA_UNSUPPORTED';
        }

        return ['provider' => $provider, 'media_count' => $count,
            'issues' => array_values(array_unique($issues)),
            'editorially_valid' => count($issues) === 0,
            'remote_publishable' => false,
            'note' => 'Metadata screening only. Provider upload, aspect ratio and ownership must be rechecked before live publishing.'];
    }
}
