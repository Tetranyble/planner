<?php

namespace App\Services\Content;

use App\Authorization\BrandPermissionGuard;
use App\Domain\Content\PlatformVariantRules;
use App\Domain\Publishing\LiveContracts\PublishingScopePolicy;
use App\Domain\Publishing\Media\MediaPublicationProtocols;
use App\Enums\Content\ContentItemState;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\AuditEventRepositoryContract;
use App\Repository\Contracts\ContentApprovalRepositoryContract;
use App\Repository\Contracts\ContentStudioRepositoryContract;
use App\Repository\Contracts\CreativeImageHandoffRepositoryContract;
use App\Repository\Contracts\PublicationReadinessRepositoryContract;
use App\Services\Publishing\ExecutePublicationPlans;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** The same authorization applies to HTTP, UTCP/MCP and background callers. */
final class ManageContentStudio
{
    public function __construct(
        private readonly BrandPermissionGuard $guard,
        private readonly ContentStudioRepositoryContract $repository,
        private readonly StorageMediaInspector $inspector,
        private readonly ContentApprovalRepositoryContract $approvals,
        private readonly AuditEventRepositoryContract $audit,
        private readonly PublicationReadinessRepositoryContract $connections,
        private readonly CreativeImageHandoffRepositoryContract $creativeLineage,
    ) {}

    public function can(User $actor, Organisation $org, Brand $brand, string $permission): bool
    {
        return (int) $brand->organisation_id === (int) $org->id
            && $this->guard->allows($actor, $brand, $permission);
    }

    private function check(User $actor, Organisation $org, Brand $brand, string $permission): void
    {
        if (! $this->can($actor, $org, $brand, $permission)) {
            throw new AuthorizationException('Brand content permission denied.');
        }
    }

    private function transaction(User $actor, Organisation $org, Brand $brand, string $permission, callable $action): mixed
    {
        $this->check($actor, $org, $brand, $permission);

        return DB::transaction(function () use ($actor, $org, $brand, $permission, $action) {
            Organisation::query()->whereKey($org->id)->lockForUpdate()->firstOrFail();
            $locked = Brand::query()->where('organisation_id', $org->id)->whereKey($brand->id)->lockForUpdate()->firstOrFail();
            $this->check($actor, $org, $locked, $permission);

            return $action();
        }, 3);
    }

    public function listing(User $actor, Organisation $org, Brand $brand): array
    {
        $this->check($actor, $org, $brand, 'content_item.index');
        $items = $this->repository->paginate($org, $brand, 20);
        $library = $this->can($actor, $org, $brand, 'media_asset.index')
            ? $this->repository->media($org, $brand) : collect();
        $generatedIds = $this->creativeLineage->generatedMediaIds($org, $brand, $library->pluck('id')->all());

        return [
            'items' => $items->through(fn ($item) => [
                'uuid' => $item->uuid, 'title' => $item->title, 'kind' => $item->content_kind,
                'state' => $item->state, 'created_at' => $item->created_at?->toIso8601String(),
            ])->toArray(),
            'media' => $this->can($actor, $org, $brand, 'media_asset.index')
                ? $library->map(fn ($m) => [
                    'uuid' => $m->uuid, 'file_name' => $m->file_name, 'mime_type' => $m->mime_type,
                    'ai_generated_source' => in_array((int) $m->id, $generatedIds, true),
                ])->values()->all() : [],
            'accounts' => $this->can($actor, $org, $brand, 'social_account.index')
                ? $this->repository->accounts($org, $brand)->map(fn ($a) => [
                    'uuid' => $a->uuid, 'provider' => $a->provider, 'display_name' => $a->display_name,
                ])->values()->all() : [],
        ];
    }

    public function detail(User $actor, Organisation $org, Brand $brand, string $uuid): array
    {
        $this->check($actor, $org, $brand, 'content_item.view');
        $item = $this->repository->findItem($org, $brand, $uuid);

        return ['item' => ['uuid' => $item->uuid, 'title' => $item->title, 'kind' => $item->content_kind, 'state' => $item->state],
            'versions' => $this->repository->versions($org, $brand, $item)->map(fn ($v) => [
                'uuid' => $v->uuid, 'version_number' => $v->version_number, 'copy_text' => $v->copy_text,
                'content_hash' => $v->content_hash,
                'ai_generated' => $v->ai_generated, 'includes_sensitive_claim' => $v->includes_sensitive_claim,
                'media' => $v->mediaLinks->map(fn ($link) => [
                    'uuid' => $link->asset?->uuid, 'name' => $link->asset?->file_name, 'role' => $link->role, 'sort_order' => $link->sort_order,
                ])->values()->all(),
            ])->values()->all(),
            'targets' => $this->can($actor, $org, $brand, 'content_target.view')
                ? $this->repository->targets($org, $brand, $item)->map(fn ($target) => [
                    'uuid' => $target->uuid, 'platform_caption' => $target->platform_caption, 'publishing_mode' => $target->publishing_mode,
                    'payload_hash' => $target->payload_hash, 'version_uuid' => $target->version->uuid,
                ])->values()->all() : [],
            'media' => $this->can($actor, $org, $brand, 'media_asset.index')
                ? $this->repository->media($org, $brand)->map(fn ($m) => ['uuid' => $m->uuid, 'file_name' => $m->file_name])->values()->all() : [],
            'accounts' => $this->can($actor, $org, $brand, 'social_account.index')
                ? $this->repository->accounts($org, $brand)->map(fn ($a) => ['uuid' => $a->uuid, 'display_name' => $a->display_name, 'provider' => $a->provider])->values()->all() : [],
        ];
    }

    private function ensureUsableMedia(Organisation $org, Brand $brand, array $uuids): void
    {
        foreach ($this->repository->findMediaForVersion($org, $brand, $uuids) as $asset) {
            if (! $asset->storage_media_id) {
                throw new ConflictHttpException('An attached asset is not linked to canonical Storage media.');
            }
            $this->inspector->inspectCanonical($org, (int) $asset->storage_media_id);
        }
    }

    /** A generated-image origin cannot be hidden by an unknown/false draft declaration. */
    private function assertAiSourceDisclosure(Organisation $org, Brand $brand, array $data): void
    {
        $uuids = $data['media_uuids'] ?? [];
        if (! $uuids) {
            return;
        }
        $assetIds = $this->repository->findMediaForVersion($org, $brand, $uuids)->pluck('id')->all();
        if ($this->creativeLineage->generatedMediaIds($org, $brand, $assetIds)
            && ($data['ai_generated'] ?? null) !== true) {
            throw ValidationException::withMessages(['ai_generated' => 'This draft includes a confirmed AI-generated image. Declare AI-generated material as Yes.']);
        }
    }

    public function create(User $actor, Organisation $org, Brand $brand, array $data): array
    {
        return $this->transaction($actor, $org, $brand, 'content_item.store', function () use ($actor, $org, $brand, $data) {
            $this->ensureUsableMedia($org, $brand, $data['media_uuids'] ?? []);
            $this->assertAiSourceDisclosure($org, $brand, $data);
            $item = $this->repository->createItem($org, $brand, $actor, $data);
            $version = $this->repository->appendVersion($org, $brand, $item, $actor, 1, $data['copy_text'], $data['media_uuids'] ?? [], $data['ai_generated'] ?? null, $data['includes_sensitive_claim'] ?? null);
            $this->audit->record($org, $actor, 'content.item.created', 'content_item', $item->uuid, $brand, ['version' => 1]);

            return ['uuid' => $item->uuid, 'version_uuid' => $version->uuid, 'version_number' => 1, 'state' => ContentItemState::DRAFT->value];
        });
    }

    public function append(User $actor, Organisation $org, Brand $brand, string $uuid, array $data): array
    {
        return $this->transaction($actor, $org, $brand, 'content_version.store', function () use ($actor, $org, $brand, $uuid, $data) {
            $item = $this->repository->findItem($org, $brand, $uuid, true);
            if ($item->state !== ContentItemState::DRAFT->value) {
                throw new ConflictHttpException('Only draft items can accept new versions.');
            }
            $last = $this->repository->latestVersionNumber($org, $brand, $item);
            if ($last !== (int) $data['expected_version']) {
                throw new ConflictHttpException('This draft was changed by another editor. Reload before saving.');
            }
            $this->ensureUsableMedia($org, $brand, $data['media_uuids'] ?? []);
            $this->assertAiSourceDisclosure($org, $brand, $data);
            $withdrawn = $this->approvals->withdrawPendingForItem($org, $brand, $item);
            $next = $last + 1;
            $version = $this->repository->appendVersion($org, $brand, $item, $actor, $next, $data['copy_text'], $data['media_uuids'] ?? [], $data['ai_generated'] ?? null, $data['includes_sensitive_claim'] ?? null);
            $this->audit->record($org, $actor, 'content.version.appended', 'content_item', $item->uuid, $brand, ['version' => $next, 'content_hash' => $version->content_hash, 'pending_approvals_withdrawn' => $withdrawn]);

            return ['uuid' => $version->uuid, 'version_number' => $next, 'content_hash' => $version->content_hash];
        });
    }

    public function linkMedia(User $actor, Organisation $org, Brand $brand, int $storageMediaId): array
    {
        $this->check($actor, $org, $brand, 'media_asset.store');
        // Validate package-owned media outside transaction; no filesystem writes occur here.
        $details = $this->inspector->inspect($org, $actor, $storageMediaId);

        return $this->transaction($actor, $org, $brand, 'media_asset.store', function () use ($actor, $org, $brand, $storageMediaId, $details) {
            $asset = $this->repository->importStorageMedia($org, $brand, $actor, $storageMediaId, $details);
            $this->audit->record($org, $actor, 'content.media.linked', 'media_asset', $asset->uuid, $brand, ['storage_media_id' => $storageMediaId]);

            return ['uuid' => $asset->uuid, 'file_name' => $asset->file_name, 'state' => $asset->asset_state];
        });
    }

    /** All-or-nothing account-specific variations against one immutable content version. */
    public function targetsBulk(User $actor, Organisation $org, Brand $brand, string $itemUuid, array $data): array
    {
        $variants = $data['variants'];
        if (count($variants) < 1 || count($variants) > 20) {
            throw ValidationException::withMessages(['variants' => 'Provide between 1 and 20 platform variations.']);
        }
        $seen = [];
        foreach ($variants as $index => $variant) {
            $account = (string) $variant['social_account_uuid'];
            if (isset($seen[$account])) {
                throw ValidationException::withMessages(['variants' => "Duplicate social account in variation $index."]);
            }
            $seen[$account] = true;
        }

        return $this->transaction($actor, $org, $brand, 'content_target.store', function () use ($actor, $org, $brand, $itemUuid, $data, $variants) {
            $item = $this->repository->findItem($org, $brand, $itemUuid, true);
            if ($item->state !== ContentItemState::DRAFT->value) {
                throw new ConflictHttpException('Only drafts can receive new variants.');
            }
            $version = $this->repository->versions($org, $brand, $item)->firstWhere('uuid', $data['version_uuid']);
            if (! $version) {
                throw new ConflictHttpException('Requested content version does not belong to this draft.');
            }
            $rows = [];
            foreach ($variants as $variant) {
                $account = $this->repository->findAccount($org, $brand, $variant['social_account_uuid']);
                $assessment = PlatformVariantRules::inspect($account->provider, $variant['platform_caption']);
                if (! $assessment['editorially_valid']) {
                    throw ValidationException::withMessages(['variants' => implode(', ', $assessment['issues']).' for '.$account->provider]);
                }
                $rows[] = ['account' => $account, 'caption' => $variant['platform_caption'], 'preflight' => $assessment];
            }
            $result = [];
            foreach ($rows as $row) {
                $uuid = $this->repository->createTarget($org, $brand, $version, $row['account'], $actor, $row['caption']);
                $this->audit->record($org, $actor, 'content.target.drafted', 'content_target', $uuid, $brand, ['provider' => $row['account']->provider, 'bulk' => true]);
                $result[] = ['uuid' => $uuid, 'provider' => $row['account']->provider, 'mode' => 'ASSISTED', 'publishable' => false, 'preflight' => $row['preflight']];
            }

            return ['created_or_reused' => count($result), 'variants' => $result, 'can_dispatch' => false];
        });
    }

    public function target(User $actor, Organisation $org, Brand $brand, string $itemUuid, array $data): array
    {
        return $this->transaction($actor, $org, $brand, 'content_target.store', function () use ($actor, $org, $brand, $itemUuid, $data) {
            $item = $this->repository->findItem($org, $brand, $itemUuid, true);
            $versions = $this->repository->versions($org, $brand, $item);
            $version = $versions->firstWhere('uuid', $data['version_uuid']);
            if (! $version) {
                throw new ConflictHttpException('Version is not part of this brand content item.');
            }
            $account = $this->repository->findAccount($org, $brand, $data['social_account_uuid']);
            $assessment = PlatformVariantRules::inspect($account->provider, $data['platform_caption']);
            if (! $assessment['editorially_valid']) {
                throw ValidationException::withMessages(['platform_caption' => implode(', ', $assessment['issues'])]);
            }
            $mode = $data['publishing_mode'] ?? 'ASSISTED';
            if ($mode === 'AUTOMATIC') {
                if (! $this->guard->allows($actor, $brand, 'approval_request.update')) {
                    throw new ConflictHttpException('Only authorized managers can create automatic targets.');
                }
                $textEligible = ExecutePublicationPlans::isEnabled()
                    && in_array($account->provider, ['facebook', 'linkedin', 'x'], true)
                    && $version->mediaLinks->isEmpty()
                    && PublishingScopePolicy::supports($account->provider, $account->account_kind)
                    && $this->connections->hasVerifiedAutomaticPublishCapability($org, $brand, $account);
                $mediaEligible = false;
                if (in_array($account->provider, ['instagram', 'tiktok'], true)
                    && $this->connections->hasVerifiedMediaPublishGrant($org, $brand, $account)
                    && $version->mediaLinks->count() === 1) {
                    $asset = $version->mediaLinks->first()?->asset;
                    if ($asset && $asset->storage_media_id
                        && MediaPublicationProtocols::validateAsset(
                            (string) $account->provider, strtolower((string) $asset->mime_type),
                            (int) $asset->byte_size, 1) === []) {
                        // Exact Storage identity still rechecked at preparation and every provider stage.
                        $this->inspector->inspectCanonical($org,(int) $asset->storage_media_id);
                        $mediaEligible = true;
                    }
                }
                if (! $textEligible && ! $mediaEligible) {
                    throw new ConflictHttpException('Automatic publishing requires verified provider entitlement, supported content and separately released execution.');
                }
                $health = $this->connections->connectionIsHealthy($org,$brand,$account);
                if (! $health['credential_valid'] || ! $health['connection_active']) {
                    throw new ConflictHttpException('Provider connection cannot support automatic targeting.');
                }
            }
            $uuid = $this->repository->createTarget($org,$brand,$version,$account,$actor,$data['platform_caption'],$mode);
            $this->audit->record($org,$actor,'content.target.drafted','content_target',$uuid,$brand,['provider' => $account->provider]);

            return ['uuid' => $uuid, 'mode' => $mode, 'publishable' => false, 'requires_fresh_approval' => true];
        });
    }
}
