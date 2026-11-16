<?php

namespace App\Repository\Eloquent;

use App\Models\ApprovalRequest;
use App\Models\Brand;
use App\Models\ContentItem;
use App\Models\ContentTarget;
use App\Models\ContentVersion;
use App\Models\MediaPublicationRun;
use App\Models\Organisation;
use App\Models\PublicationJob;
use App\Repository\Contracts\PublicationIntentRepositoryContract;
use Illuminate\Support\Collection;

/** All operator queries tenant-scoped; no provider I/O anywhere. */
final class PublicationIntentRepository implements PublicationIntentRepositoryContract
{
    public function findByOccurrence(Organisation $org, Brand $brand, ContentTarget $target, string $key): ?PublicationJob
    {
        return PublicationJob::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->where('content_target_id', $target->id)->where('occurrence_key', $key)->lockForUpdate()->first();
    }

    public function createHeld(Organisation $org, Brand $brand, ContentTarget $target, ApprovalRequest $approval, string $key, string $runAt): PublicationJob
    {
        $job = new PublicationJob;
        $job->forceFill(['organisation_id' => $org->id, 'brand_id' => $brand->id,
            'content_target_id' => $target->id, 'approval_request_id' => $approval->id,
            'occurrence_key' => $key, 'run_at' => $runAt, 'state' => 'AWAITING_USER',
            'attempt_count' => 0, 'max_attempts' => 0, 'version' => 0])->save();

        return $job;
    }

    public function listing(Organisation $org, Brand $brand, int $limit): Collection
    {
        return PublicationJob::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->orderBy('run_at')->orderBy('id')->limit($limit)->get();
    }

    public function findForInspection(Organisation $org, Brand $brand, int $id): PublicationJob
    {
        return PublicationJob::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->whereKey($id)->firstOrFail();
    }

    public function findLocked(Organisation $org, Brand $brand, int $id): PublicationJob
    {
        return PublicationJob::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    public function hasUncancelledMediaRun(Organisation $org, Brand $brand, PublicationJob $job): bool
    {
        return MediaPublicationRun::query()->where('organisation_id', $org->id)
            ->where('brand_id', $brand->id)->where('publication_job_id', $job->id)
            ->where('state', '!=', 'CANCELLED')->exists();
    }

    public function cancel(PublicationJob $job): void
    {
        $job->forceFill(['state' => 'CANCELLED', 'lease_owner' => null, 'lease_until' => null, 'version' => (int) $job->version + 1])->save();
    }

    public function context(Organisation $org, Brand $brand, PublicationJob $job): array
    {
        return $this->contextForTarget($org, $brand, (int) $job->content_target_id);
    }

    public function contextForTarget(Organisation $org, Brand $brand, int $targetId): array
    {
        $target = ContentTarget::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->whereKey($targetId)->firstOrFail();
        $version = ContentVersion::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->whereKey($target->content_version_id)->firstOrFail();
        $item = ContentItem::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->whereKey($version->content_item_id)->firstOrFail();

        return ['item_uuid' => (string) $item->uuid, 'target_uuid' => (string) $target->uuid];
    }

    public function reschedule(PublicationJob $job, string $utcTime): void
    {
        $job->forceFill(['run_at' => $utcTime, 'version' => (int) $job->version + 1])->save();
    }

    public function dueHeld(int $limit): Collection
    {
        return PublicationJob::query()->where('state', 'AWAITING_USER')->where('run_at', '<=', now())
            ->orderBy('run_at')->limit($limit)->get();
    }

    public function between(Organisation $org, Brand $brand, string $fromInclusive, string $toExclusive, int $limit): Collection
    {
        return PublicationJob::query()->where('organisation_id', $org->id)
            ->where('brand_id', $brand->id)->where('run_at', '>=', $fromInclusive)
            ->where('run_at','<',$toExclusive)->orderBy('run_at')->orderBy('id')
            ->limit(min(500,max(1,$limit)))->get();
    }
}
