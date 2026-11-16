<?php

namespace App\Domain\Publishing\Media;

/**
 * Provider-specific *offline* media delivery protocol. Does not issue HTTP requests.
 * Media origin URLs are ephemeral, never persisted in publication manifests or previews.
 * Provider endpoints must still be verified in live app review before dispatch is enabled.
 */
final class MediaPublicationProtocols
{
    public const PROVIDERS = ['instagram', 'tiktok', 'youtube'];

    public static function validateAsset(string $provider, string $mime, int $bytes, int $count): array
    {
        $issues = [];
        if (! in_array($provider, self::PROVIDERS, true)) {
            $issues[] = 'PROVIDER_UNSUPPORTED';
        }
        if ($count !== 1) {
            $issues[] = 'SINGLE_ASSET_REQUIRED';
        }
        if ($bytes < 1 || $bytes > 2_000_000_000) {
            $issues[] = 'MEDIA_SIZE_NOT_VERIFIED';
        }
        $allowed = match ($provider) {
            'instagram' => ['image/jpeg', 'video/mp4'],
            'tiktok' => ['video/mp4', 'video/quicktime'],
            'youtube' => ['video/mp4', 'video/quicktime', 'video/webm'],
            default => [],
        };
        if (! in_array($mime, $allowed, true)) {
            $issues[] = 'MEDIA_FORMAT_NOT_SUPPORTED_BY_WORKFLOW';
        }

        return array_values(array_unique($issues));
    }

    /** Does not support arbitrary external URLs: only preapproved controlled delivery origins. */
    public static function assertOrigin(string $url, array $allowedHosts): void
    {
        $parts = parse_url($url);
        $host = is_array($parts) ? strtolower((string) ($parts['host'] ?? '')) : '';
        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || ! $host
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])
            || isset($parts['fragment']) || filter_var($host, FILTER_VALIDATE_IP)
            || ! in_array($host, $allowedHosts, true)) {
            throw new \DomainException('Media delivery origin is not on the approved HTTPS host list.');
        }
    }

    /**
     * Offline request description for the first media protocol step. It never contains
     * a bearer token, file content or publicly signed Storage URL.
     */
    public static function plan(string $provider, string $mime): array
    {
        if (self::validateAsset($provider, $mime, 1, 1) !== []) {
            // Only the media MIME is known here. File size is separately checked against canonical Storage.
            throw new \DomainException('Unsupported media publishing protocol.');
        }

        return match ($provider) {
            'instagram' => [
                'mode' => str_starts_with($mime, 'video/') ? 'REELS_CONTAINER' : 'IMAGE_CONTAINER',
                'stages' => ['CREATE_CONTAINER', 'CHECK_CONTAINER_STATUS', 'PUBLISH_CONTAINER', 'VERIFY_MEDIA_RECEIPT'],
                'api_host' => 'graph.facebook.com', 'requires_verified_public_media_url' => true,
                'requires_explicit_publish_capability' => true, 'automatic_retry_after_send' => false,
            ],
            'tiktok' => [
                'mode' => 'DIRECT_POST_VIDEO_PULL',
                'stages' => ['QUERY_CREATOR_OPTIONS', 'CONFIRM_CREATOR_PRIVACY_AND_CONSENT', 'INITIALIZE_VIDEO', 'FETCH_PUBLISH_STATUS', 'VERIFY_VIDEO_RECEIPT'],
                'api_host' => 'open.tiktokapis.com', 'requires_verified_public_media_url' => true,
                'requires_explicit_publish_capability' => true, 'automatic_retry_after_send' => false,
            ],
            'youtube' => [
                'mode' => 'RESUMABLE_VIDEO_UPLOAD',
                'stages' => ['INITIALIZE_UPLOAD_SESSION', 'TRANSFER_STORAGE_BYTES', 'CHECK_UPLOAD_RESULT', 'VERIFY_VIDEO_RECEIPT'],
                'api_host' => 'www.googleapis.com', 'requires_verified_public_media_url' => false,
                'requires_canonical_storage_stream' => true, 'requires_explicit_publish_capability' => true,
                'automatic_retry_after_send' => false,
            ],
        };
    }

    /**
     * Build a provider-specific remote request *without* credentials. These specifications
     * must not be stored or exposed to browsers because they may contain signed origin URLs.
     * This method does not authorize dispatch.
     */
    public static function initRequest(string $provider, string $actorId, string $mime, string $caption,
        ?string $originUrl = null, array $allowedOrigins = [], ?array $creatorConsent = null): array
    {
        if (self::validateAsset($provider, $mime, 1, 1) !== [] || ($provider === 'instagram' && ! preg_match('/^[0-9]{3,30}$/D', $actorId))) {
            throw new \DomainException('Invalid media actor or MIME type.');
        }
        if (strlen($caption) > 2200) {
            throw new \DomainException('Caption too long for conservative media publishing profile.');
        }
        if (in_array($provider, ['instagram', 'tiktok'], true)) {
            if ($originUrl === null) {
                throw new \DomainException('Canonical media distribution URL is required.');
            }
            self::assertOrigin($originUrl, $allowedOrigins);
        }
        if ($provider === 'instagram') {
            $media = str_starts_with($mime, 'video/')
                ? ['media_type' => 'REELS', 'video_url' => $originUrl]
                : ['image_url' => $originUrl];

            return ['method' => 'POST', 'host' => 'graph.facebook.com', 'path' => '/{graph_version}/'.$actorId.'/media',
                'body' => array_merge($media, ['caption' => $caption])];
        }
        if ($provider === 'tiktok') {
            if (! ($creatorConsent['explicit_consent'] ?? false)
                || ! in_array($creatorConsent['privacy_level'] ?? null, $creatorConsent['allowed_privacy_levels'] ?? [], true)
                || ! ($creatorConsent['creator_options_checked'] ?? false)) {
                throw new \DomainException('Current TikTok creator options and explicit user privacy consent are required.');
            }

            return ['method' => 'POST', 'host' => 'open.tiktokapis.com', 'path' => '/v2/post/publish/video/init/',
                'body' => ['post_info' => ['title' => $caption, 'privacy_level' => $creatorConsent['privacy_level'],
                    'disable_duet' => (bool) ($creatorConsent['disable_duet'] ?? false),
                    'disable_stitch' => (bool) ($creatorConsent['disable_stitch'] ?? false),
                    'disable_comment' => (bool) ($creatorConsent['disable_comment'] ?? false),
                    'is_aigc' => (bool) ($creatorConsent['is_aigc'] ?? false)],
                    'source_info' => ['source' => 'PULL_FROM_URL', 'video_url' => $originUrl]]];
        }

        return ['method' => 'POST', 'host' => 'www.googleapis.com', 'path' => '/upload/youtube/v3/videos',
            'query' => ['uploadType' => 'resumable', 'part' => 'snippet,status'],
            'headers' => ['X-Upload-Content-Type' => $mime],
            'body' => ['snippet' => ['title' => self::youtubeTitle($caption), 'description' => $caption],
                'status' => ['privacyStatus' => 'private']]];
    }

    /** Status polling is read-only with respect to post creation and must be bounded by callers. */
    public static function statusRequest(string $provider, string $operationId): array
    {
        if (! preg_match('/^[a-zA-Z0-9_~.:-]{2,160}$/D', $operationId)) {
            throw new \DomainException('Provider operation reference is invalid.');
        }

        return match ($provider) {
            'instagram' => ['method' => 'GET', 'host' => 'graph.facebook.com',
                'path' => '/{graph_version}/'.$operationId, 'query' => ['fields' => 'status_code']],
            'tiktok' => ['method' => 'POST', 'host' => 'open.tiktokapis.com',
                'path' => '/v2/post/publish/status/fetch/', 'body' => ['publish_id' => $operationId]],
            default => throw new \DomainException('Status operation not supported in this media protocol.'),
        };
    }

    public static function finalizeInstagram(string $actorId, string $containerId): array
    {
        if (! preg_match('/^[0-9]{3,30}$/D', $actorId)
            || ! preg_match('/^[0-9]{3,30}$/D', $containerId)) {
            throw new \DomainException('Instagram publishing identifiers are invalid.');
        }

        return ['method' => 'POST', 'host' => 'graph.facebook.com',
            'path' => '/{graph_version}/'.$actorId.'/media_publish',
            'body' => ['creation_id' => $containerId]];
    }

    /** Read-only verification, always against a pinned host and a previously obtained reference. */
    public static function verifyRequest(string $provider, string $reference): array
    {
        if (! preg_match('/^[a-zA-Z0-9_~.:-]{2,160}$/D', $reference)) {
            throw new \DomainException('Provider verification reference is invalid.');
        }
        if ($provider === 'instagram') {
            if (! preg_match('/^[0-9]{3,30}$/D', $reference)) {
                throw new \DomainException('Instagram media ID must be numeric.');
            }

            return ['method' => 'GET', 'host' => 'graph.facebook.com',
                'path' => '/{graph_version}/'.$reference, 'query' => ['fields' => 'id,owner']];
        }
        if ($provider === 'tiktok') {
            return self::statusRequest('tiktok', $reference);
        }
        throw new \DomainException('Verified media lookup is unsupported.');
    }

    public static function youtubeTitle(string $caption): string
    {
        $line = trim(explode("\n", $caption, 2)[0]);
        if ($line === '') {
            throw new \DomainException('YouTube title required.');
        }

        return function_exists('mb_substr') ? mb_substr($line, 0, 100, 'UTF-8') : substr($line, 0, 100);
    }

    /** Responses are only classified; no record is considered published without a provider receipt. */
    public static function providerState(string $provider, string $operation, array $reply): array
    {
        if (! in_array($provider, self::PROVIDERS, true)) {
            return ['state' => 'UNCERTAIN', 'code' => 'UNKNOWN_PROVIDER'];
        }
        if ($provider === 'instagram') {
            if ($operation === 'CREATE_CONTAINER') {
                return self::receipt($reply['id'] ?? null, 'CONTAINER_CREATED');
            }
            if ($operation === 'CHECK_CONTAINER_STATUS') {
                return match ($reply['status_code'] ?? null) {
                    'FINISHED' => ['state' => 'READY_TO_FINALIZE', 'code' => 'MEDIA_PROCESSED'],
                    'IN_PROGRESS' => ['state' => 'PROCESSING', 'code' => 'PROCESSING'],
                    'ERROR','EXPIRED' => ['state' => 'FAILED', 'code' => 'PROVIDER_MEDIA_FAILED'],
                    default => ['state' => 'UNCERTAIN', 'code' => 'UNKNOWN_CONTAINER_STATUS'],
                };
            }
            if ($operation === 'PUBLISH_CONTAINER') {
                return self::receipt($reply['id'] ?? null, 'PUBLISHED');
            }
            if ($operation === 'VERIFY_MEDIA_RECEIPT') {
                $result = self::receipt($reply['id'] ?? null, 'VERIFIED');
                $owner = $reply['owner']['id'] ?? null;
                if (! is_string($owner) || ! preg_match('/^[0-9]{3,30}$/D', $owner)) {
                    return ['state' => 'UNCERTAIN', 'code' => 'INSTAGRAM_OWNER_NOT_VERIFIED'];
                }
                $result['owner_id'] = $owner;

                return $result;
            }
        }
        if ($provider === 'tiktok') {
            if ($operation === 'INITIALIZE_VIDEO') {
                if (($reply['error']['code'] ?? null) !== 'ok') {
                    return ['state' => 'UNCERTAIN', 'code' => 'TIKTOK_INIT_NOT_CONFIRMED'];
                }

                return self::receipt($reply['data']['publish_id'] ?? null, 'INITIALIZED');
            }
            if ($operation === 'VERIFY_VIDEO_RECEIPT') {
                if (($reply['data']['status'] ?? null) !== 'PUBLISH_COMPLETE') {
                    return ['state' => 'PROCESSING', 'code' => 'VIDEO_NOT_YET_VERIFIED'];
                }

                return self::receipt(isset($reply['data']['publicaly_available_post_id'][0])
                    ? (string) $reply['data']['publicaly_available_post_id'][0] : null, 'VERIFIED');
            }
            if ($operation === 'FETCH_PUBLISH_STATUS') {
                return match ($reply['data']['status'] ?? null) {
                    'PUBLISH_COMPLETE' => self::receipt(isset($reply['data']['publicaly_available_post_id'][0]) ? (string) $reply['data']['publicaly_available_post_id'][0] : null, 'PUBLISHED'),
                    'FAILED' => ['state' => 'FAILED', 'code' => 'PROVIDER_REJECTED'],
                    'PROCESSING_UPLOAD','PROCESSING_DOWNLOAD' => ['state' => 'PROCESSING', 'code' => 'PROVIDER_PROCESSING'],
                    default => ['state' => 'UNCERTAIN', 'code' => 'UNRECOGNIZED_TIKTOK_STATUS'],
                };
            }
        }
        if ($provider === 'youtube' && $operation === 'UPLOAD_COMPLETE') {
            return self::receipt($reply['id'] ?? null, 'UPLOAD_CONFIRMED');
        }

        return ['state' => 'UNCERTAIN', 'code' => 'UNRECOGNIZED_OPERATION'];
    }

    private static function receipt(mixed $id, string $state): array
    {
        if (! is_string($id) || ! preg_match('/^[a-zA-Z0-9_~.:-]{2,160}$/D',$id)) {
            return ['state' => 'UNCERTAIN', 'code' => 'MISSING_VALID_PROVIDER_RECEIPT'];
        }

        return ['state' => $state, 'code' => 'PROVIDER_RECEIPT_PRESENT', 'receipt' => $id];
    }
}
