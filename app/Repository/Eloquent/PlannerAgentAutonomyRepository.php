<?php

namespace App\Repository\Eloquent;

use App\Domain\Agent\AgentDelegationPolicy;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\PlannerAgentAutonomousEvent;
use App\Models\PlannerAgentAutonomousRun;
use App\Models\PlannerAgentDelegation;
use App\Models\User;
use App\Repository\Contracts\PlannerAgentAutonomyRepositoryContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class PlannerAgentAutonomyRepository implements PlannerAgentAutonomyRepositoryContract
{
    private function event(PlannerAgentAutonomousRun $run, string $name, array $evidence = []): void
    {
        PlannerAgentAutonomousEvent::query()->create(['run_id' => $run->id, 'event' => $name, 'evidence' => $evidence, 'created_at' => now('UTC')]);
    }

    public function queue(Organisation $org, Brand $brand, User $actor, PlannerAgentDelegation $grant, array $payload): PlannerAgentAutonomousRun
    {
        $hash = AgentDelegationPolicy::canonicalHash('research.autorun', $payload);
        $existing = PlannerAgentAutonomousRun::query()->where('delegation_id', $grant->id)->where('idempotency_key', $payload['run_key'])->first();
        if ($existing) {
            if (! hash_equals($existing->request_hash, $hash)) {
                throw new ConflictHttpException('Research run key is already used.');
            }

            return $existing;
        }
        $run = PlannerAgentAutonomousRun::query()->create([
            'uuid' => (string) Str::uuid(), 'organisation_id' => $org->id, 'brand_id' => $brand->id, 'delegation_id' => $grant->id,
            'requested_by_user_id' => $actor->id, 'idempotency_key' => $payload['run_key'], 'request_hash' => $hash,
            'title' => $payload['title'], 'objective' => $payload['objective'], 'resource_kind' => $payload['resource_kind'],
            'status' => 'QUEUED_RESEARCH']);
        $this->event($run, 'research.queued', ['request_hash' => $hash, 'external_io' => false]);

        return $run;
    }

    public function find(Organisation $org, Brand $brand, PlannerAgentDelegation $grant, string $uuid, bool $lock = false): PlannerAgentAutonomousRun
    {
        return PlannerAgentAutonomousRun::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->where('delegation_id', $grant->id)->where('uuid', $uuid)
            ->when($lock, fn ($q) => $q->lockForUpdate())->firstOrFail();
    }

    public function list(Organisation $org, Brand $brand, int $limit = 40, ?int $delegationId = null): array
    {
        return PlannerAgentAutonomousRun::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->when($delegationId, fn ($q) => $q->where('delegation_id', $delegationId))
            ->latest('id')->limit(min(100, max(1, $limit)))->get()->map(fn ($r) => $this->summary($r))->all();
    }

    private function summary(PlannerAgentAutonomousRun $r): array
    {
        return ['uuid' => $r->uuid, 'title' => $r->title, 'objective' => $r->objective, 'resource_kind' => $r->resource_kind,
            'status' => $r->status, 'delegation_id' => (int) $r->delegation_id, 'sources' => $r->sources, 'findings' => $r->findings, 'resource_text' => $r->resource_text,
            'research_hash' => $r->research_hash, 'resource_hash' => $r->resource_hash,
            'resource_revision_count' => (int) $r->resource_revision_count, 'work_order_uuid' => $r->work_order_uuid,
            'content_uuid' => $r->content_uuid, 'failure_code' => $r->failure_code,
            'created_at' => $r->created_at?->toIso8601String(), 'sources_verified' => false];
    }

    public function claim(): ?array
    {
        return DB::transaction(function (): ?array {
            $r = PlannerAgentAutonomousRun::query()->whereIn('status', ['QUEUED_RESEARCH', 'QUEUED_RESOURCE'])
                ->orderBy('id')->lock('for update skip locked')->first();
            if (! $r) {
                return null;
            }
            $research = $r->status === 'QUEUED_RESEARCH';
            $attemptField = $research ? 'research_attempts' : 'resource_attempts';
            if ((int) $r->{$attemptField} >= 1) {
                $r->forceFill(['status' => 'UNCERTAIN', 'failure_code' => 'ATTEMPT_LIMIT'])->save();
                $this->event($r, 'execution.quarantined', ['reason' => 'ATTEMPT_LIMIT']);

                return null;
            }
            $fence = (string) Str::uuid();
            $r->forceFill(['status' => $research ? 'RESEARCHING' : 'GENERATING_RESOURCE',
                $attemptField => (int) $r->{$attemptField} + 1, 'lease_token' => $fence, 'lease_expires_at' => now('UTC')->addMinutes(3)])->save();
            $this->event($r, 'execution.claimed', ['stage' => $research ? 'RESEARCH' : 'RESOURCE', 'attempt' => (int) $r->{$attemptField}]);

            return ['id' => $r->id, 'stage' => $research ? 'RESEARCH' : 'RESOURCE', 'fence' => $fence, 'run' => $r];
        }, 3);
    }

    private function fenced(int $id, string $fence, string $expected): ?PlannerAgentAutonomousRun
    {
        return PlannerAgentAutonomousRun::query()->whereKey($id)->where('status', $expected)
            ->where('lease_token', $fence)->where('lease_expires_at', '>', now('UTC'))->lockForUpdate()->first();
    }

    public function completeResearch(int $id, string $fence, array $evidence): bool
    {
        return DB::transaction(function () use ($id, $fence, $evidence): bool {
            $r = $this->fenced($id, $fence, 'RESEARCHING');
            if (! $r) {
                return false;
            }
            $r->forceFill(['status' => 'QUEUED_RESOURCE', 'findings' => $evidence['findings'], 'sources' => $evidence['sources'],
                'research_hash' => $evidence['research_hash'], 'lease_token' => null, 'lease_expires_at' => null])->save();
            $this->event($r, 'research.completed', ['research_hash' => $r->research_hash, 'source_count' => count($evidence['sources']),
                'verified' => false, 'publication_authorized' => false]);

            return true;
        }, 3);
    }

    public function completeResource(int $id, string $fence, array $resource): bool
    {
        return DB::transaction(function () use ($id, $fence, $resource): bool {
            $r = $this->fenced($id, $fence, 'GENERATING_RESOURCE');
            if (! $r) {
                return false;
            }
            $text = trim((string) $resource['text']);
            if ($text === '' || mb_strlen($text) > 12000) {
                throw new \InvalidArgumentException('Resource must be nonempty and under the bounded size.');
            }
            $r->forceFill(['status' => 'READY_FOR_REVIEW', 'resource_text' => $text, 'resource_hash' => hash('sha256', $text),
                'resource_model' => (string) $resource['model'], 'lease_token' => null, 'lease_expires_at' => null])->save();
            $this->event($r, 'resource.completed', ['resource_hash' => $r->resource_hash, 'publication_authorized' => false]);

            return true;
        }, 3);
    }

    public function quarantine(int $id, string $fence, string $code): bool
    {
        return DB::transaction(function () use ($id, $fence, $code): bool {
            $r = PlannerAgentAutonomousRun::query()->whereKey($id)->where('lease_token', $fence)
                ->whereIn('status', ['RESEARCHING', 'GENERATING_RESOURCE'])->lockForUpdate()->first();
            if (! $r) {
                return false;
            }
            $r->forceFill(['status' => 'UNCERTAIN', 'failure_code' => $code, 'lease_token' => null, 'lease_expires_at' => null])->save();
            $this->event($r, 'execution.uncertain', ['code' => $code, 'safe_to_retry' => false]);

            return true;
        }, 3);
    }

    public function quarantineExpired(int $limit): int
    {
        $ids = PlannerAgentAutonomousRun::query()->whereIn('status', ['RESEARCHING', 'GENERATING_RESOURCE'])
            ->where('lease_expires_at', '<=', now('UTC'))->orderBy('id')->limit(min($limit, 100))->pluck('id');
        $count = 0;
        foreach ($ids as $id) {
            $done = DB::transaction(function () use ($id): bool {
                $r = PlannerAgentAutonomousRun::query()->whereKey($id)->whereIn('status', ['RESEARCHING', 'GENERATING_RESOURCE'])
                    ->where('lease_expires_at', '<=', now('UTC'))->lockForUpdate()->first();
                if (! $r) {
                    return false;
                }
                $r->forceFill(['status' => 'UNCERTAIN', 'failure_code' => 'LEASE_EXPIRED', 'lease_token' => null, 'lease_expires_at' => null])->save();
                $this->event($r, 'execution.uncertain', ['code' => 'LEASE_EXPIRED', 'safe_to_retry' => false]);

                return true;
            }, 3);
            if ($done) {
                $count++;
            }
        }

        return $count;
    }

    /** Only a first-party, authenticated human owner/manager may invoke this method. */
    public function revise(Organisation $org, Brand $brand, PlannerAgentDelegation $grant, User $actor,
        string $uuid, string $expectedHash, string $text, string $reason): array
    {
        return DB::transaction(function () use ($org, $brand, $grant, $actor, $uuid, $expectedHash, $text, $reason): array {
            $r = $this->find($org, $brand, $grant, $uuid, true);
            if ($r->status !== 'READY_FOR_REVIEW' || ! $r->resource_text || ! $r->resource_hash) {
                throw new ConflictHttpException('Only unpromoted review-ready resources can be revised.');
            }
            if (! hash_equals((string) $r->resource_hash, hash('sha256', (string) $r->resource_text))) {
                throw new ConflictHttpException('Stored resource integrity check failed.');
            }
            if (! hash_equals((string) $r->resource_hash, $expectedHash)) {
                throw new ConflictHttpException('The resource changed; reload before saving edits.');
            }
            $revision = (int) $r->resource_revision_count;
            if ($revision >= 12) {
                throw new ConflictHttpException('Maximum human resource revisions reached.');
            }
            $new = trim($text);
            if ($new === '' || mb_strlen($new) < 20 || mb_strlen($new) > 6000) {
                throw new \InvalidArgumentException('Human-reviewed resource length is invalid.');
            }
            $hash = hash('sha256', $new);
            if (! hash_equals($hash, (string) $r->resource_hash)) {
                $before = $r->resource_hash;
                $r->forceFill(['resource_text' => $new, 'resource_hash' => $hash, 'resource_revision_count' => $revision + 1])->save();
                $this->event($r, 'resource.revised', ['previous_sha256' => $before, 'current_sha256' => $hash,
                    'revision' => $revision + 1, 'reviewer_user_id' => (int) $actor->id, 'review_note' => $reason,
                    'research_hash' => $r->research_hash, 'human_approval_required' => true]);
            }

            return ['run_uuid' => $r->uuid, 'resource_hash' => $r->resource_hash,
                'resource_revision_count' => (int) $r->resource_revision_count, 'requires_human_approval' => true];
        }, 3);
    }

    public function promoted(PlannerAgentAutonomousRun $r, string $workOrderUuid, string $contentUuid): void
    {
        $r->forceFill(['status' => 'DRAFT_CREATED', 'work_order_uuid' => $workOrderUuid, 'content_uuid' => $contentUuid])->save();
        $this->event($r, 'resource.promoted', ['work_order_uuid' => $workOrderUuid, 'content_uuid' => $contentUuid, 'human_approval_required' => true]);
    }

    public function cancel(Organisation $org, Brand $brand, PlannerAgentAutonomousRun $run): bool
    {
        $r = PlannerAgentAutonomousRun::query()->whereKey($run->id)->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->lockForUpdate()->firstOrFail();
        if (! in_array($r->status, ['QUEUED_RESEARCH', 'QUEUED_RESOURCE', 'READY_FOR_REVIEW'], true)) {
            return false;
        }
        $r->forceFill(['status' => 'CANCELLED'])->save();
        $this->event($r, 'execution.cancelled', ['external_action' => false]);

        return true;
    }
}
