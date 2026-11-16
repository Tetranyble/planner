<?php

namespace App\Repository\Eloquent;

use App\Models\ApprovalRequest;
use App\Models\Brand;
use App\Models\MediaAsset;
use App\Models\MediaPublicationPreparation;
use App\Models\MediaPublicationPreparationEvent;
use App\Models\Organisation;
use App\Models\PublicationJob;
use App\Models\User;
use App\Repository\Contracts\MediaPublicationPreparationRepositoryContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** All persistence is brand scoped. No HTTP or media-streaming operations here. */
final class MediaPublicationPreparationRepository implements MediaPublicationPreparationRepositoryContract
{
    public function list(Organisation $org, Brand $brand, int $limit): Collection
    {
        return MediaPublicationPreparation::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->orderByDesc('id')->limit(max(1, min($limit, 100)))->get();
    }

    public function find(Organisation $org, Brand $brand, int $id, bool $lock = false): MediaPublicationPreparation
    {
        return MediaPublicationPreparation::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->whereKey($id)->when($lock, fn ($query) => $query->lockForUpdate())->firstOrFail();
    }

    public function existing(Organisation $org, Brand $brand, PublicationJob $job): ?MediaPublicationPreparation
    {
        return MediaPublicationPreparation::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->where('publication_job_id', $job->id)->lockForUpdate()->first();
    }

    public function create(Organisation $org, Brand $brand, PublicationJob $job, MediaAsset $asset, ApprovalRequest $approval,
        User $actor, array $manifest, ?string $blocker): MediaPublicationPreparation
    {
        $protocol = $manifest['mode'] ?? ($manifest['provider'] === 'youtube' ? 'YOUTUBE_RESUMABLE' : null);
        if (! is_string($protocol) || $protocol === '') {
            throw new \InvalidArgumentException('A media publication protocol is required.');
        }

        $row = new MediaPublicationPreparation;
        $row->forceFill([
            'uuid' => (string) Str::uuid(), 'organisation_id' => $org->id, 'brand_id' => $brand->id,
            'publication_job_id' => $job->id, 'media_asset_id' => $asset->id, 'prepared_by_user_id' => $actor->id,
            'provider' => $manifest['provider'], 'protocol' => $protocol, 'state' => 'PREPARED',
            'media_sha256' => $asset->sha256, 'approval_payload_hash' => $approval->payload_hash,
            'protocol_manifest' => $manifest, 'blocker_code' => $blocker, 'version' => 1,
        ])->save();
        $this->event($row, 'PREPARE', null, 'PREPARED', $actor, ['provider' => $row->provider, 'remote_dispatch' => false]);

        return $row;
    }

    public function abort(MediaPublicationPreparation $row, User $actor): void
    {
        $old = $row->state;
        $row->forceFill(['state' => 'ABORTED', 'version' => $row->version + 1, 'blocker_code' => 'OPERATOR_ABORTED'])->save();
        $this->event($row, 'ABORT', $old, 'ABORTED', $actor, ['remote_dispatch' => false]);
    }

    private function event(MediaPublicationPreparation $row, string $action, ?string $from, string $to, User $actor, array $details): void
    {
        (new MediaPublicationPreparationEvent)->forceFill([
            'organisation_id' => $row->organisation_id, 'brand_id' => $row->brand_id,
            'media_publication_preparation_id' => $row->id, 'action' => $action,
            'from_state' => $from, 'to_state' => $to, 'actor_user_id' => $actor->id, 'details' => $details,
        ])->save();
    }
}
