<?php

namespace App\Domain\Publishing\Media;

/** Capability requirements, not an authorization token or proof that provider review passed. */
final class MediaReleaseRequirements
{
    public static function forProvider(string $provider): array
    {
        return match ($provider) {
            'instagram' => ['instagram_content_publish', 'instagram_basic', 'pages_show_list'],
            'tiktok' => ['video.publish'],
            'youtube' => ['https://www.googleapis.com/auth/youtube.upload'],
            default => [],
        };
    }

    public static function checklist(string $provider, bool $appReviewed, bool $mediaDistributionVerified,
        bool $workerReleased): array
    {
        if (! in_array($provider, MediaPublicationProtocols::PROVIDERS, true)) {
            throw new \DomainException('Unsupported media provider.');
        }
        $blocked = [];
        if (! $appReviewed) {
            $blocked[] = 'PROVIDER_APP_REVIEW_NOT_VERIFIED';
        }
        if (! $mediaDistributionVerified) {
            $blocked[] = 'STORAGE_MEDIA_DELIVERY_NOT_VERIFIED';
        }
        if (! $workerReleased) {
            $blocked[] = 'MEDIA_WORKER_NOT_RELEASED';
        }
        // A stage-fenced worker exists, but final provider receipt reconciliation is
        // deliberately NOT accepted as complete publication delivery.
        $blocked[] = $provider === 'youtube' ? 'YOUTUBE_RESUMABLE_STORAGE_STREAM_UNVERIFIED'
            : 'REMOTE_MEDIA_RECEIPT_VERIFICATION_NOT_RELEASED';
        $blocked[] = 'TETRANYBLE_DISTRIBUTION_ADAPTER_UNVERIFIED';

        return ['provider' => $provider, 'required_scopes' => self::forProvider($provider),
            'release_blockers' => $blocked, 'can_publish' => false];
    }
}
