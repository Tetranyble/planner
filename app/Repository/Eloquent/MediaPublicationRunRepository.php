<?php

namespace App\Repository\Eloquent;

use App\Domain\Publishing\Media\MediaStageTransition;
use App\Models\ApprovalRequest;
use App\Models\Brand;
use App\Models\ContentTarget;
use App\Models\MediaAsset;
use App\Models\MediaPublicationPreparation;
use App\Models\MediaPublicationRun;
use App\Models\MediaPublicationRunEvent;
use App\Models\MediaPublicationStageAttempt;
use App\Models\Organisation;
use App\Models\PublicationAttempt;
use App\Models\PublicationEvent;
use App\Models\PublicationJob;
use App\Models\PublishedPost;
use App\Models\User;
use App\Models\YouTubeResumableTransfer;
use App\Repository\Contracts\MediaPublicationRunRepositoryContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** Persist every stage before external I/O. No outbound call ever happens inside a transaction. */
final class MediaPublicationRunRepository implements MediaPublicationRunRepositoryContract
{
    public function list(Organisation $org, Brand $brand, int $limit): Collection
    {
        return MediaPublicationRun::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->orderByDesc('id')->limit(max(1, min(100, $limit)))->get();
    }

    public function find(Organisation $org, Brand $brand, int $id, bool $lock = false): MediaPublicationRun
    {
        return MediaPublicationRun::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->whereKey($id)->when($lock, fn ($q) => $q->lockForUpdate())->firstOrFail();
    }

    public function create(Organisation $org, Brand $brand, MediaPublicationPreparation $prep, User $actor): MediaPublicationRun
    {
        $existing = MediaPublicationRun::query()->where('organisation_id', $org->id)
            ->where('brand_id', $brand->id)->where('media_publication_preparation_id', $prep->id)
            ->lockForUpdate()->first();
        if ($existing) {
            return $existing;
        }
        if ($prep->state !== 'PREPARED' || (int) $prep->organisation_id !== (int) $org->id
            || (int) $prep->brand_id !== (int) $brand->id) {
            throw new ConflictHttpException('Media preparation is not eligible for an execution run.');
        }
        $row = (new MediaPublicationRun)->forceFill([
            'uuid' => (string) Str::uuid(), 'organisation_id' => $org->id, 'brand_id' => $brand->id,
            'media_publication_preparation_id' => $prep->id, 'publication_job_id' => $prep->publication_job_id,
            'provider' => $prep->provider, 'state' => 'HELD', 'next_operation' => MediaStageTransition::first($prep->provider),
            'version' => 1, 'stage_sequence' => 0, 'poll_count' => 0, 'blocker_code' => 'MEDIA_RELEASE_DISABLED',
        ]);
        $row->save();
        $this->event($row, 'RUN_CREATED', null, 'HELD', $actor->id, ['remote_sent' => false]);

        return $row;
    }

    public function arm(MediaPublicationRun $row, User $actor, int $version): MediaPublicationRun
    {
        if ($row->state !== 'HELD' || (int) $row->version !== $version || $row->lease_owner !== null
            || $row->attempts()->exists()) {
            throw new ConflictHttpException('Run changed or was previously attempted; cannot re-arm.');
        }
        $from = $row->state;
        $row->forceFill(['state' => 'ARMED', 'armed_by_user_id' => $actor->id, 'blocker_code' => null,
            'next_check_at' => now(), 'version' => $row->version + 1])->save();
        $this->event($row, 'OPERATOR_ARMED', $from, 'ARMED', $actor->id, ['auto_retry' => false]);

        return $row;
    }

    public function cancel(MediaPublicationRun $run, User $actor, int $version): MediaPublicationRun
    {
        if ($run->provider === 'youtube' && YouTubeResumableTransfer::query()
            ->where('organisation_id', $run->organisation_id)->where('media_publication_run_id', $run->id)
            ->where('state', '!=', 'HELD')->exists()) {
            throw new ConflictHttpException('Active YouTube transfer must be reconciled before cancellation.');
        }
        if (! in_array($run->state, ['HELD', 'ARMED'], true) || (int) $run->version !== $version
            || $run->lease_owner !== null || $run->attempts()->exists()) {
            throw new ConflictHttpException('A leased or previously attempted media run cannot be cancelled.');
        }
        $old = $run->state;
        $run->forceFill(['state' => 'CANCELLED', 'blocker_code' => 'OPERATOR_CANCELLED',
            'next_check_at' => null, 'version' => $run->version + 1])->save();
        $this->event($run, 'OPERATOR_CANCELLED', $old, 'CANCELLED', $actor->id, ['remote_sent' => false]);

        return $run;
    }

    public function dueIds(int $limit): array
    {
        return MediaPublicationRun::query()->whereIn('state', ['ARMED', 'NEXT_STAGE', 'WAITING_PROVIDER', 'AWAITING_VERIFICATION'])
            ->where('next_check_at', '<=', now())->whereNull('lease_owner')->orderBy('next_check_at')
            ->limit(max(1, min($limit, 100)))->pluck('id')->map(fn ($x) => (int) $x)->all();
    }

    public function claim(int $id, string $owner): ?MediaPublicationRun
    {
        return DB::transaction(function () use ($id, $owner) {
            $row = MediaPublicationRun::query()->whereKey($id)->lockForUpdate()->first();
            if (! $row || ! in_array($row->state, ['ARMED', 'NEXT_STAGE', 'WAITING_PROVIDER', 'AWAITING_VERIFICATION'], true)
                || $row->lease_owner !== null || ! $row->next_check_at || $row->next_check_at->isFuture()) {
                return null;
            }
            if ($row->provider === 'youtube') {
                return null;
            } // canonical resumable streaming not implemented
            if (in_array($row->state, ['WAITING_PROVIDER', 'AWAITING_VERIFICATION'], true)
                && ! MediaStageTransition::isProbe($row->next_operation)) {
                return null;
            }
            $from = $row->state;
            $row->forceFill(['state' => 'LEASED', 'lease_owner' => $owner, 'lease_until' => now()->addMinutes(2),
                'version' => $row->version + 1])->save();
            $this->event($row, 'WORKER_CLAIMED', $from, 'LEASED', null, ['remote_sent' => false]);

            return $row;
        }, 3);
    }

    public function holdClaim(int $id, string $owner, string $code): bool
    {
        return DB::transaction(function () use ($id, $owner, $code) {
            $row = MediaPublicationRun::query()->whereKey($id)->where('state', 'LEASED')
                ->where('lease_owner', $owner)->where('lease_until', '>', now())->lockForUpdate()->first();
            if (! $row) {
                return false;
            }
            $safe = preg_match('/^[A-Z0-9_]{2,70}$/D', $code) ? $code : 'MEDIA_PREFLIGHT_BLOCKED';
            $row->forceFill(['state' => 'HELD', 'lease_owner' => null, 'lease_until' => null,
                'next_check_at' => null, 'blocker_code' => $safe, 'version' => $row->version + 1])->save();
            $this->event($row, 'PREFLIGHT_HOLD', 'LEASED', 'HELD', null, ['code' => $safe, 'remote_sent' => false]);

            return true;
        }, 3);
    }

    public function stageIsFenced(int $id, string $owner, int $attemptId): bool
    {
        return MediaPublicationRun::query()->whereKey($id)->where('state', 'DISPATCHING')
            ->where('lease_owner', $owner)->where('lease_until', '>', now())
            ->whereHas('attempts', fn ($q) => $q->whereKey($attemptId)->where('state', 'STARTED'))
            ->exists();
    }

    public function startStage(int $id, string $owner): ?array
    {
        return DB::transaction(function () use ($id, $owner) {
            $row = MediaPublicationRun::query()->whereKey($id)->where('lease_owner', $owner)->where('state', 'LEASED')
                ->where('lease_until', '>', now())->lockForUpdate()->first();
            if (! $row) {
                return null;
            }
            // Mutating operations may only ever be attempted ONCE. Read-only probes are bounded.
            $probe = MediaStageTransition::isProbe($row->next_operation);
            if (! $probe && $row->attempts()->where('operation', $row->next_operation)->exists()) {
                return null;
            }
            if ($probe && $row->poll_count >= 12) {
                return null;
            }
            $seq = $row->stage_sequence + 1;
            $attempt = (new MediaPublicationStageAttempt)->forceFill([
                'organisation_id' => $row->organisation_id, 'brand_id' => $row->brand_id,
                'media_publication_run_id' => $row->id, 'stage_sequence' => $seq,
                'operation' => $row->next_operation, 'state' => 'STARTED',
            ]);
            $attempt->save();
            $row->forceFill(['state' => 'DISPATCHING', 'stage_sequence' => $seq,
                'poll_count' => $probe ? $row->poll_count + 1 : $row->poll_count, 'version' => $row->version + 1])->save();
            $this->event($row, 'STAGE_STARTED', 'LEASED', 'DISPATCHING', null,
                ['operation' => $attempt->operation, 'sequence' => $seq, 'remote_may_have_received_request' => true]);

            return ['attempt_id' => (int) $attempt->id, 'operation' => $attempt->operation,
                'provider' => $row->provider, 'run_id' => (int) $row->id,
                'organisation_id' => (int) $row->organisation_id, 'brand_id' => (int) $row->brand_id,
                'publication_job_id' => (int) $row->publication_job_id,
                'armed_by_user_id' => (int) $row->armed_by_user_id,
                'remote_reference' => $row->provider_operation_reference];
        }, 3);
    }

    public function finishStage(int $id, string $owner, int $attemptId, array $result): bool
    {
        return DB::transaction(function () use ($id, $owner, $attemptId, $result) {
            $row = MediaPublicationRun::query()->whereKey($id)->where('lease_owner', $owner)
                ->where('state', 'DISPATCHING')->where('lease_until', '>', now())->lockForUpdate()->first();
            if (! $row) {
                return false;
            }
            $attempt = MediaPublicationStageAttempt::query()->where('organisation_id', $row->organisation_id)
                ->where('brand_id', $row->brand_id)->where('media_publication_run_id', $row->id)
                ->whereKey($attemptId)->where('stage_sequence', $row->stage_sequence)
                ->where('state', 'STARTED')->lockForUpdate()->first();
            if (! $attempt) {
                return false;
            }
            $next = MediaStageTransition::next($row->provider, $attempt->operation, $result, $row->poll_count);
            $newState = $next['state'];
            if ($newState === 'CONFIRMED') {
                $expected = (string) $row->verified_post_reference;
                if ($expected === '' || ! hash_equals($expected, (string) ($next['receipt'] ?? ''))) {
                    $newState = 'UNCERTAIN';
                    $next = ['operation' => $attempt->operation, 'code' => 'REMOTE_RECEIPT_MISMATCH'];
                } else {
                    $problem = $this->recordVerifiedPublication($row, (string) $next['receipt'], $result);
                    if ($problem !== null) {
                        $newState = 'UNCERTAIN';
                        $next = ['operation' => $attempt->operation, 'code' => $problem];
                    } else {
                        $newState = 'COMPLETED';
                        $next['operation'] = 'COMPLETE';
                    }
                }
            }
            $code = preg_match('/^[A-Z0-9_]{2,70}$/D', $next['code'] ?? '') ? $next['code'] : 'MEDIA_STAGE_UNKNOWN';
            $attempt->forceFill(['state' => $newState, 'outcome_code' => $code, 'finished_at' => now()])->save();
            $updates = ['state' => $newState, 'next_operation' => $next['operation'], 'lease_owner' => null,
                'lease_until' => null, 'blocker_code' => $code, 'version' => $row->version + 1,
                'next_check_at' => in_array($newState, ['NEXT_STAGE', 'WAITING_PROVIDER', 'AWAITING_VERIFICATION'], true)
                    ? now()->addSeconds($newState === 'NEXT_STAGE' ? 1 : 600) : null];
            if (isset($next['receipt'])) {
                if ($newState === 'AWAITING_VERIFICATION') {
                    $updates['verified_post_reference'] = $next['receipt'];
                } elseif ($newState === 'NEXT_STAGE') {
                    $updates['provider_operation_reference'] = $next['receipt'];
                }
            }
            if ($newState === 'NEXT_STAGE') {
                $updates['poll_count'] = 0;
            }
            $row->forceFill($updates)->save();
            $this->propagateTerminalToPublication($row, $newState, $code);
            $this->event($row, 'PROVIDER_STAGE_OUTCOME', 'DISPATCHING', $newState, null,
                ['operation' => $attempt->operation, 'sequence' => $row->stage_sequence, 'code' => $code,
                    'receipt_present' => isset($next['receipt'])]);

            return true;
        }, 3);
    }

    /** Called only after a second independently verified provider read, under the stage lease. */
    private function recordVerifiedPublication(MediaPublicationRun $run, string $receipt, array $outcome): ?string
    {
        $job = PublicationJob::query()->where('organisation_id', $run->organisation_id)
            ->where('brand_id', $run->brand_id)->whereKey($run->publication_job_id)->lockForUpdate()->first();
        $prep = MediaPublicationPreparation::query()->where('organisation_id', $run->organisation_id)
            ->where('brand_id', $run->brand_id)->whereKey($run->media_publication_preparation_id)->first();
        if (! $job || ! $prep || $prep->state !== 'PREPARED' || $job->state !== 'AWAITING_USER'
            || (int) $job->attempt_count !== 0 || $job->run_at->isFuture()) {
            return 'FINAL_APPROVAL_OR_PLAN_CHANGED';
        }
        $approval = ApprovalRequest::query()->where('organisation_id', $run->organisation_id)
            ->where('brand_id', $run->brand_id)->whereKey($job->approval_request_id)->first();
        $asset = MediaAsset::query()->where('organisation_id', $run->organisation_id)
            ->where('brand_id', $run->brand_id)->whereKey($prep->media_asset_id)->first();
        if (! $approval || $approval->status !== 'APPROVED' || ! $asset
            || ! hash_equals((string) $prep->approval_payload_hash, (string) $approval->payload_hash)
            || ! hash_equals((string) $prep->media_sha256, (string) $asset->sha256)) {
            return 'FINAL_MEDIA_EVIDENCE_CHANGED';
        }
        $target = ContentTarget::query()->where('organisation_id', $run->organisation_id)
            ->where('brand_id', $run->brand_id)->whereKey($job->content_target_id)->first();
        $account = $target?->account()->where('organisation_id', $run->organisation_id)
            ->where('brand_id', $run->brand_id)->where('provider', $run->provider)->where('status', 'ACTIVE')->first();
        if (! $account) {
            return 'FINAL_PROVIDER_ACCOUNT_MISMATCH';
        }
        if ($run->provider === 'youtube' && ! hash_equals((string) $account->external_account_id,
            (string) ($outcome['provider_owner_id'] ?? ''))) {
            return 'YOUTUBE_POST_CHANNEL_MISMATCH';
        }
        if ($run->provider === 'instagram' && ! hash_equals((string) $account->external_account_id,
            (string) ($outcome['provider_owner_id'] ?? ''))) {
            return 'INSTAGRAM_POST_OWNER_MISMATCH';
        }
        if (PublishedPost::query()->where('organisation_id', $run->organisation_id)
            ->where('social_account_id', $account->id)->where('external_post_id', $receipt)->lockForUpdate()->exists()) {
            return 'REMOTE_POST_RECEIPT_COLLISION';
        }
        if (PublicationAttempt::query()->where('organisation_id', $run->organisation_id)
            ->where('brand_id', $run->brand_id)->where('publication_job_id', $job->id)->lockForUpdate()->exists()) {
            return 'PUBLICATION_ATTEMPT_ALREADY_EXISTS';
        }
        (new PublicationAttempt)->forceFill([
            'uuid' => (string) Str::uuid(), 'organisation_id' => $run->organisation_id,
            'brand_id' => $run->brand_id, 'publication_job_id' => $job->id,
            'attempt_number' => 1, 'operation_key' => hash('sha256', 'media-run:v1:'.$run->uuid),
            'provider_request_id' => $receipt, 'started_at' => $run->attempts()->orderBy('id')->value('created_at') ?? now(),
            'finished_at' => now(), 'result' => 'SUCCESS',
            'response_redacted' => ['provider' => $run->provider, 'verified' => true, 'stage_count' => $run->stage_sequence],
        ])->save();
        (new PublishedPost)->forceFill([
            'organisation_id' => $run->organisation_id, 'brand_id' => $run->brand_id,
            'publication_job_id' => $job->id, 'social_account_id' => $account->id,
            'provider' => $run->provider, 'external_post_id' => $receipt,
            'published_at' => now(), 'remote_status' => 'PUBLISHED',
        ])->save();
        $old = $job->state;
        $job->forceFill(['state' => 'PUBLISHED', 'attempt_count' => 1, 'max_attempts' => 1,
            'last_error_code' => null, 'last_error_message' => null, 'version' => (int) $job->version + 1])->save();
        (new PublicationEvent)->forceFill([
            'uuid' => (string) Str::uuid(), 'organisation_id' => $run->organisation_id,
            'brand_id' => $run->brand_id, 'publication_job_id' => $job->id,
            'from_state' => $old, 'to_state' => 'PUBLISHED', 'event_type' => 'MEDIA_POST_VERIFIED',
            'actor_type' => 'SYSTEM', 'actor_ref' => null, 'occurred_at' => now(),
            'details' => ['provider' => $run->provider, 'verification' => 'SECOND_PROVIDER_READ', 'run_id' => $run->id],
        ])->save();

        return null;
    }

    /**
     * Terminal media results must also close the parent plan, otherwise an uncertain
     * remote send looks like an unattempted AWAITING_USER job to the operator desk.
     * Reuse the existing two-person publication reconciliation for UNCERTAIN jobs.
     */
    private function propagateTerminalToPublication(MediaPublicationRun $run, string $runState, string $code): void
    {
        if (! in_array($runState, ['FAILED', 'UNCERTAIN', 'MANUAL_REQUIRED'], true)) {
            return;
        }
        $job = PublicationJob::query()->where('organisation_id', $run->organisation_id)
            ->where('brand_id', $run->brand_id)->whereKey($run->publication_job_id)->lockForUpdate()->first();
        if (! $job || $job->state !== 'AWAITING_USER') {
            return;
        }
        $state = $runState === 'FAILED' ? 'FAILED' : 'UNCERTAIN';
        $safe = preg_match('/^[A-Z0-9_]{2,70}$/D', $code) ? $code : 'MEDIA_EXECUTION_UNRESOLVED';
        if (PublicationAttempt::query()->where('organisation_id', $run->organisation_id)
            ->where('brand_id', $run->brand_id)->where('publication_job_id', $job->id)->lockForUpdate()->exists()) {
            throw new ConflictHttpException('A publication attempt already owns this job.');
        }
        (new PublicationAttempt)->forceFill([
            'uuid' => (string) Str::uuid(), 'organisation_id' => $run->organisation_id,
            'brand_id' => $run->brand_id, 'publication_job_id' => $job->id,
            'attempt_number' => 1, 'operation_key' => hash('sha256', 'media-run:v1:'.$run->uuid),
            'started_at' => $run->attempts()->orderBy('id')->value('created_at') ?? now(),
            'finished_at' => now(), 'result' => $state === 'FAILED' ? 'FAILED' : 'UNCERTAIN',
            'error_code' => $safe, 'response_redacted' => ['provider' => $run->provider,
                'run_id' => $run->id, 'last_stage' => $run->next_operation, 'remote_outcome_unverified' => true],
        ])->save();
        $job->forceFill(['state' => $state, 'attempt_count' => 1, 'max_attempts' => 1,
            'last_error_code' => $safe, 'last_error_message' => null, 'version' => (int) $job->version + 1])->save();
        (new PublicationEvent)->forceFill([
            'uuid' => (string) Str::uuid(), 'organisation_id' => $run->organisation_id,
            'brand_id' => $run->brand_id, 'publication_job_id' => $job->id,
            'from_state' => 'AWAITING_USER', 'to_state' => $state,
            'event_type' => 'MEDIA_EXECUTION_TERMINAL', 'actor_type' => 'SYSTEM', 'actor_ref' => null,
            'occurred_at' => now(), 'details' => ['run_id' => $run->id, 'run_state' => $runState,
                'reason_code' => $safe, 'remote_dispatch_repeat_allowed' => false],
        ])->save();
    }

    /** Called within the transfer transaction ONLY after upload + independent video/channel verification. */
    public function completeVerifiedYouTube(int $runId, string $videoId, string $channelId): ?string
    {
        $run = MediaPublicationRun::query()->whereKey($runId)->lockForUpdate()->first();
        if (! $run || $run->provider !== 'youtube' || $run->state !== 'ARMED'
            || $run->attempts()->exists()) {
            return 'YOUTUBE_MEDIA_RUN_CHANGED';
        }
        $problem = $this->recordVerifiedPublication($run, $videoId, ['provider_owner_id' => $channelId]);
        if ($problem !== null) {
            return $problem;
        }
        $run->forceFill(['state' => 'COMPLETED', 'next_operation' => 'COMPLETE', 'next_check_at' => null,
            'verified_post_reference' => $videoId, 'blocker_code' => 'PROVIDER_MEDIA_VERIFIED',
            'version' => $run->version + 1])->save();
        $this->event($run, 'YOUTUBE_VIDEO_VERIFIED', 'ARMED', 'COMPLETED', null,
            ['provider_receipt_verified' => true, 'channel_verified' => true]);

        return null;
    }

    public function quarantineYouTube(int $runId, string $code): void
    {
        $run = MediaPublicationRun::query()->whereKey($runId)->lockForUpdate()->first();
        if (! $run || $run->provider !== 'youtube' || ! in_array($run->state, ['ARMED', 'HELD'], true)) {
            return;
        }
        $safe = preg_match('/^[A-Z0-9_]{2,70}$/D', $code) ? $code : 'YOUTUBE_TRANSFER_UNCERTAIN';
        $old = $run->state;
        $run->forceFill(['state' => 'UNCERTAIN', 'blocker_code' => $safe, 'next_check_at' => null,
            'version' => $run->version + 1])->save();
        $this->propagateTerminalToPublication($run, 'UNCERTAIN', $safe);
        $this->event($run, 'YOUTUBE_TRANSFER_QUARANTINED', $old, 'UNCERTAIN', null,
            ['code' => $safe, 'automatic_replay' => false]);
    }

    public function quarantineExpired(int $limit): array
    {
        $counts = ['held' => 0, 'uncertain' => 0];
        foreach (MediaPublicationRun::query()->whereIn('state', ['LEASED', 'DISPATCHING'])
            ->where('lease_until', '<', now())->orderBy('id')->limit(max(1, min(100, $limit)))->pluck('id') as $id) {
            DB::transaction(function () use ($id, &$counts) {
                $row = MediaPublicationRun::query()->whereKey($id)->lockForUpdate()->first();
                if (! $row || ! in_array($row->state, ['LEASED', 'DISPATCHING'], true)
                    || ! $row->lease_until || $row->lease_until->isFuture()) {
                    return;
                }
                $old = $row->state;
                $started = $old === 'DISPATCHING' || $row->attempts()->where('state', 'STARTED')->exists();
                $next = $started ? 'UNCERTAIN' : 'HELD';
                $row->forceFill(['state' => $next, 'lease_owner' => null, 'lease_until' => null,
                    'next_check_at' => null, 'blocker_code' => $started ? 'LEASE_EXPIRED_AFTER_SEND' : 'LEASE_EXPIRED_BEFORE_SEND',
                    'version' => $row->version + 1])->save();
                if ($started) {
                    $this->propagateTerminalToPublication($row,$next,'LEASE_EXPIRED_AFTER_SEND');
                }
                $this->event($row,'LEASE_RECOVERY',$old,$next,null,['remote_may_have_received_request' => $started]);
                $counts[$started ? 'uncertain' : 'held']++;
            }, 3);
        }

        return $counts;
    }

    private function event(MediaPublicationRun $run,string $action,?string $from,string $to,?int $actor,array $safe): void
    {
        (new MediaPublicationRunEvent)->forceFill([
            'organisation_id' => $run->organisation_id, 'brand_id' => $run->brand_id, 'media_publication_run_id' => $run->id,
            'action' => $action, 'from_state' => $from, 'to_state' => $to, 'actor_user_id' => $actor, 'details' => $safe,
        ])->save();
    }
}
