<?php

namespace App\Repository\Eloquent;

use App\Models\PublicationJob;
use App\Models\PublicationSandboxEvent;
use App\Models\PublicationSandboxRun;
use App\Repository\Contracts\PublicationSandboxRepositoryContract;
use Illuminate\Support\Collection;

final class PublicationSandboxRepository implements PublicationSandboxRepositoryContract
{
    public function heldJob(int $org, int $brand, int $job): PublicationJob
    {
        return PublicationJob::query()->where('organisation_id', $org)->where('brand_id', $brand)->whereKey($job)->lockForUpdate()->firstOrFail();
    }

    public function findOperation(int $org, int $job, string $key): ?PublicationSandboxRun
    {
        return PublicationSandboxRun::query()->where('organisation_id', $org)->where('publication_job_id', $job)->where('operation_key', $key)->lockForUpdate()->first();
    }

    public function queue(PublicationJob $job, string $key, string $provider, string $scenario): PublicationSandboxRun
    {
        $run = new PublicationSandboxRun;
        $run->forceFill(['organisation_id' => $job->organisation_id, 'brand_id' => $job->brand_id,
            'publication_job_id' => $job->id, 'operation_key' => $key, 'provider' => $provider, 'scenario' => $scenario, 'status' => 'QUEUED', 'attempt_count' => 0])->save();

        return $run;
    }

    public function findNext(): ?PublicationSandboxRun
    {
        return PublicationSandboxRun::query()->where(function ($q) {
            $q->where('status', 'QUEUED')
                ->orWhere(fn ($q) => $q->where('status', 'LEASED')->where('lease_until', '<', now()));
        })
            ->orderBy('id')->lock('for update skip locked')->first();
    }

    public function lockRun(int $id): ?PublicationSandboxRun
    {
        return PublicationSandboxRun::query()->whereKey($id)->lockForUpdate()->first();
    }

    public function event(PublicationSandboxRun $run, string $name, array $details = []): void
    {
        $e = new PublicationSandboxEvent;
        $e->forceFill(['publication_sandbox_run_id' => $run->id, 'event' => $name, 'safe_details' => $details])->save();
    }

    public function history(int $org, int $brand, int $job, int $limit): Collection
    {
        return PublicationSandboxRun::query()->where('organisation_id', $org)->where('brand_id', $brand)->where('publication_job_id', $job)->orderByDesc('id')->limit($limit)->get();
    }
}
