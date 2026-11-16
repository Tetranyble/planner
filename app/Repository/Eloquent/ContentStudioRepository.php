<?php

namespace App\Repository\Eloquent;

use App\Domain\Content\ContentVersionPayload;
use App\Enums\Connection\SocialAccountStatus;
use App\Enums\Content\ContentItemState;
use App\Enums\Content\MediaAssetState;
use App\Models\Brand;
use App\Models\Campaign;
use App\Models\ContentItem;
use App\Models\ContentSeries;
use App\Models\ContentTarget;
use App\Models\ContentVersion;
use App\Models\ContentVersionMedia;
use App\Models\MediaAsset;
use App\Models\Organisation;
use App\Models\SocialAccount;
use App\Models\User;
use App\Repository\Contracts\ContentStudioRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

final class ContentStudioRepository implements ContentStudioRepositoryContract
{
    public function paginate(Organisation $organisation, Brand $brand, int $pageSize): LengthAwarePaginator
    {
        return $this->items($organisation, $brand)->orderByDesc('id')->paginate($pageSize);
    }

    private function items(Organisation $organisation, Brand $brand)
    {
        return ContentItem::query()->where('organisation_id', $organisation->id)->where('brand_id', $brand->id);
    }

    public function findItem(Organisation $organisation, Brand $brand, string $uuid, bool $lock = false): ContentItem
    {
        return $this->items($organisation, $brand)->where('uuid', $uuid)
            ->when($lock, fn ($query) => $query->lockForUpdate())->firstOrFail();
    }

    public function versions(Organisation $organisation, Brand $brand, ContentItem $item): Collection
    {
        return ContentVersion::query()->where('organisation_id', $organisation->id)
            ->where('brand_id', $brand->id)->where('content_item_id', $item->id)
            ->with('mediaLinks.asset')->orderByDesc('version_number')->get();
    }

    public function media(Organisation $organisation, Brand $brand): Collection
    {
        return MediaAsset::query()->where('organisation_id', $organisation->id)
            ->where('brand_id', $brand->id)->where('asset_state', MediaAssetState::READY->value)
            ->orderByDesc('id')->limit(100)->get();
    }

    public function accounts(Organisation $organisation, Brand $brand): Collection
    {
        return SocialAccount::query()->where('organisation_id', $organisation->id)
            ->where('brand_id', $brand->id)->where('status', SocialAccountStatus::ACTIVE->value)
            ->orderBy('display_name')->get();
    }

    public function campaign(Organisation $organisation, Brand $brand, ?string $uuid): ?int
    {
        if (! $uuid) {
            return null;
        }

        return (int) Campaign::query()->where('organisation_id', $organisation->id)
            ->where('brand_id', $brand->id)->where('uuid', $uuid)->firstOrFail()->id;
    }

    public function series(Organisation $organisation, Brand $brand, ?string $uuid): ?int
    {
        if (! $uuid) {
            return null;
        }

        return (int) ContentSeries::query()->where('organisation_id', $organisation->id)
            ->where('brand_id', $brand->id)->where('uuid', $uuid)->firstOrFail()->id;
    }

    public function createItem(Organisation $organisation, Brand $brand, User $actor, array $input): ContentItem
    {
        $item = new ContentItem;
        $item->forceFill([
            'organisation_id' => $organisation->id, 'brand_id' => $brand->id,
            'campaign_id' => $this->campaign($organisation, $brand, $input['campaign_uuid'] ?? null),
            'content_series_id' => $this->series($organisation, $brand, $input['series_uuid'] ?? null),
            'title' => $input['title'], 'content_kind' => $input['content_kind'],
            'state' => ContentItemState::DRAFT->value, 'created_by_user_id' => $actor->id,
        ])->save();

        return $item;
    }

    public function latestVersionNumber(Organisation $organisation, Brand $brand, ContentItem $item): int
    {
        return (int) ContentVersion::query()->where('organisation_id', $organisation->id)
            ->where('brand_id', $brand->id)->where('content_item_id', $item->id)->max('version_number');
    }

    public function findMediaForVersion(Organisation $organisation, Brand $brand, array $uuids): Collection
    {
        if (! $uuids) {
            return collect();
        }
        $media = MediaAsset::query()->where('organisation_id', $organisation->id)->where('brand_id', $brand->id)
            ->where('asset_state', MediaAssetState::READY->value)
            ->whereIn('uuid', $uuids)->get()->keyBy('uuid');
        if ($media->count() !== count($uuids)) {
            throw (new ModelNotFoundException)->setModel(MediaAsset::class);
        }

        return collect($uuids)->map(fn ($uuid) => $media->get($uuid));
    }

    public function appendVersion(Organisation $organisation, Brand $brand, ContentItem $item, User $actor, int $number, string $text, array $mediaUuids, ?bool $aiGenerated = null, ?bool $includesSensitiveClaim = null): ContentVersion
    {
        $assets = $this->findMediaForVersion($organisation, $brand, $mediaUuids);
        $version = new ContentVersion;
        $version->forceFill([
            'organisation_id' => $organisation->id, 'brand_id' => $brand->id, 'content_item_id' => $item->id,
            'version_number' => $number, 'copy_text' => $text, 'body_json' => ContentVersionPayload::body(),
            'content_hash' => ContentVersionPayload::hash($text, $mediaUuids, $aiGenerated, $includesSensitiveClaim),
            'ai_generated' => $aiGenerated, 'includes_sensitive_claim' => $includesSensitiveClaim,
            'created_by_user_id' => $actor->id,
        ])->save();
        foreach ($assets as $index => $asset) {
            $link = new ContentVersionMedia;
            $link->forceFill([
                'organisation_id' => $organisation->id, 'brand_id' => $brand->id,
                'content_version_id' => $version->id, 'media_asset_id' => $asset->id,
                'sort_order' => $index, 'role' => $index === 0 ? 'PRIMARY' : 'SECONDARY',
            ])->save();
        }

        return $version;
    }

    public function storageMediaAsset(Organisation $organisation, Brand $brand, int $storageMediaId): ?MediaAsset
    {
        return MediaAsset::query()->where('organisation_id', $organisation->id)->where('brand_id', $brand->id)
            ->where('storage_media_id', $storageMediaId)->first();
    }

    public function importStorageMedia(Organisation $organisation, Brand $brand, User $actor, int $storageMediaId, array $details): MediaAsset
    {
        $asset = $this->storageMediaAsset($organisation, $brand, $storageMediaId);
        if ($asset) {
            return $asset;
        }
        $asset = new MediaAsset;
        $asset->forceFill([
            'organisation_id' => $organisation->id, 'brand_id' => $brand->id,
            'storage_media_id' => $storageMediaId, 'file_name' => $details['file_name'],
            'mime_type' => $details['mime_type'], 'byte_size' => $details['byte_size'],
            'sha256' => $details['sha256'], 'width' => $details['width'], 'height' => $details['height'],
            'asset_state' => MediaAssetState::READY->value, 'source_type' => 'UPLOAD',
            'created_by_user_id' => $actor->id,
        ])->save();

        return $asset;
    }

    public function findAccount(Organisation $organisation, Brand $brand, string $uuid): SocialAccount
    {
        return SocialAccount::query()->where('organisation_id', $organisation->id)
            ->where('brand_id', $brand->id)->where('uuid', $uuid)
            ->where('status', SocialAccountStatus::ACTIVE->value)->firstOrFail();
    }

    public function targets(Organisation $organisation, Brand $brand, ContentItem $item): Collection
    {
        $versionIds = ContentVersion::query()->where('organisation_id', $organisation->id)
            ->where('brand_id', $brand->id)->where('content_item_id', $item->id)->select('id');

        return ContentTarget::query()->where('organisation_id', $organisation->id)
            ->where('brand_id', $brand->id)->whereIn('content_version_id', $versionIds)
            ->with('version')->orderByDesc('id')->get();
    }

    public function createTarget(Organisation $organisation, Brand $brand, ContentVersion $version, SocialAccount $account, User $actor, string $caption, string $mode = 'ASSISTED'): string
    {
        if (! in_array($mode, ['ASSISTED', 'AUTOMATIC'], true)) {
            throw new \InvalidArgumentException('Invalid publishing mode');
        }
        $body = ['schema_version' => 1, 'format' => 'plain_text', 'account_provider' => $account->provider];
        // Preserve every historic ASSISTED hash; new AUTOMATIC targets get a distinct signed payload.
        $payloadHash = hash('sha256', json_encode($mode === 'ASSISTED'
            ? [$version->content_hash, $account->uuid, $caption, $body]
            : [$version->content_hash, $account->uuid, $caption, $body, 'AUTOMATIC'], JSON_THROW_ON_ERROR));
        $existing = ContentTarget::query()->where('organisation_id', $organisation->id)
            ->where('brand_id', $brand->id)->where('content_version_id', $version->id)
            ->where('social_account_id', $account->id)->where('payload_hash', $payloadHash)->first();
        if ($existing) {
            return $existing->uuid;
        }
        $target = new ContentTarget;
        $target->forceFill([
            'organisation_id' => $organisation->id, 'brand_id' => $brand->id,
            'content_version_id' => $version->id, 'social_account_id' => $account->id,
            'platform_caption' => $caption, 'payload_json' => $body, 'payload_hash' => $payloadHash,
            // Never assert provider publishing eligibility from OAuth alone.
            'publishing_mode' => $mode,
            'created_by_user_id' => $actor->id,
        ])->save();

        return $target->uuid;
    }
}
