<?php

namespace App\Domain\Content;

use App\Models\BrandApprovalPolicy;
use App\Models\ContentItem;
use App\Models\ContentTarget;

/** Deterministic snapshot. Publishing MUST recompute it and recheck media/account/policy state. */
final class ApprovalPayloadSnapshot
{
    public static function capture(ContentItem $item, ContentTarget $target, BrandApprovalPolicy $policy, array $matchedRules = []): array
    {
        $version = $target->version;
        $links = $version->mediaLinks->sortBy('sort_order')->values();

        $snapshot = [
            'schema_version' => 1,
            'item_uuid' => (string) $item->uuid,
            'version_uuid' => (string) $version->uuid,
            'version_number' => (int) $version->version_number,
            'content_hash' => (string) $version->content_hash,
            'target_uuid' => (string) $target->uuid,
            'target_payload_hash' => (string) $target->payload_hash,
            'social_account_uuid' => (string) $target->account->uuid,
            'provider' => (string) $target->account->provider,
            'media' => $links->map(fn ($link) => [
                'asset_uuid' => (string) $link->asset->uuid,
                'storage_media_id' => (int) $link->asset->storage_media_id,
                'sha256' => (string) $link->asset->sha256,
                'sort_order' => (int) $link->sort_order,
            ])->all(),
            'approval_policy_uuid' => (string) $policy->uuid,
            'approval_policy_version' => (int) $policy->policy_version,
            'matched_publish_rules' => $matchedRules,
        ];
        // Preserve the exact legacy snapshot for unclassified historical versions.
        if ($version->ai_generated !== null || $version->includes_sensitive_claim !== null) {
            $snapshot['risk_declarations'] = [
                'ai_generated' => $version->ai_generated,
                'includes_sensitive_claim' => $version->includes_sensitive_claim,
            ];
        }

        return $snapshot;
    }

    public static function hash(array $snapshot): string
    {
        return hash('sha256', json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
