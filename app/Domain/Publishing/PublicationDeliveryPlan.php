<?php

namespace App\Domain\Publishing;

use App\Domain\Content\ProviderPublishCapabilityMatrix;

/** A pure, offline adapter plan. Never creates HTTP clients or grants delivery approval. */
final class PublicationDeliveryPlan
{
    public static function preview(string $provider, string $mediaKind, bool $hasCredential, bool $hasApproval): array
    {
        $provider = strtolower($provider);
        $kind = strtoupper($mediaKind);
        $capability = ProviderPublishCapabilityMatrix::capabilityFor($provider);
        $allowed = match ($provider) {
            'instagram','tiktok','youtube' => ['IMAGE', 'VIDEO', 'CAROUSEL'],
            'facebook','linkedin','x' => ['TEXT', 'IMAGE', 'VIDEO', 'CAROUSEL'],
            default => [],
        };
        // Media compatibility is an advisory preflight, not a provider API guarantee.
        $constraints = [];
        if (! $capability) {
            $constraints[] = 'UNKNOWN_PROVIDER';
        }
        if ($capability && ! in_array($kind, $allowed, true)) {
            $constraints[] = 'MEDIA_TYPE_REQUIRES_PROVIDER_VALIDATION';
        }
        if (! $hasCredential) {
            $constraints[] = 'MISSING_CREDENTIAL';
        }
        if (! $hasApproval) {
            $constraints[] = 'MISSING_APPROVAL';
        }
        $constraints[] = 'REMOTE_PUBLISHING_NOT_ENABLED';

        return ['provider' => $provider, 'media_kind' => $kind, 'required_capability' => $capability,
            'checks' => $constraints, 'network_requests' => 0, 'may_dispatch' => false,
            'note' => 'Offline advisory only; verified provider permissions, quotas, channel-specific media specs and remote idempotency remain outstanding.'];
    }
}
