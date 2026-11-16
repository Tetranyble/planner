<?php

namespace App\Repository\Eloquent;

use App\Domain\Publishing\YouTube\ResumableUploadProtocol as Protocol;
use App\Models\Brand;
use App\Models\MediaAsset;
use App\Models\MediaPublicationRun;
use App\Models\Organisation;
use App\Models\User;
use App\Models\YouTubeResumableAttempt;
use App\Models\YouTubeResumableEvent;
use App\Models\YouTubeResumableTransfer;
use App\Repository\Contracts\MediaPublicationRunRepositoryContract;
use App\Repository\Contracts\YouTubeTransferRepositoryContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** Eloquent-only scoped persistence; all provider I/O is performed outside DB transactions. */
final class YouTubeTransferRepository implements YouTubeTransferRepositoryContract
{
    public function __construct(private readonly MediaPublicationRunRepositoryContract $publicationRuns) {}

    public function list(Organisation $org, Brand $brand, int $limit): Collection
    {
        return YouTubeResumableTransfer::query()->where('organisation_id', $org->id)
            ->where('brand_id', $brand->id)->orderByDesc('id')->limit(max(1, min(100, $limit)))->get();
    }

    public function find(Organisation $org, Brand $brand, int $id): YouTubeResumableTransfer
    {
        return YouTubeResumableTransfer::query()->where('organisation_id', $org->id)
            ->where('brand_id', $brand->id)->whereKey($id)->firstOrFail();
    }

    public function create(Organisation $org, Brand $brand, MediaPublicationRun $run, MediaAsset $asset, User $actor): YouTubeResumableTransfer
    {
        if ($run->provider !== 'youtube' || $run->state !== 'HELD' || $run->attempts()->exists()
            || (int) $run->organisation_id !== (int) $org->id || (int) $run->brand_id !== (int) $brand->id
            || (int) $asset->organisation_id !== (int) $org->id || (int) $asset->brand_id !== (int) $brand->id) {
            throw new ConflictHttpException('The media run is not eligible for a YouTube transfer.');
        }
        $old = YouTubeResumableTransfer::query()->where('organisation_id', $org->id)
            ->where('media_publication_run_id', $run->id)->lockForUpdate()->first();
        if ($old) {
            if (! hash_equals($old->canonical_sha256, (string) $asset->sha256)
                || (int) $old->media_asset_id !== (int) $asset->id) {
                throw new ConflictHttpException('Previously bound video evidence changed.');
            }

            return $old;
        }
        Protocol::assertMedia((int) $asset->byte_size, (string) $asset->mime_type);
        $transfer = (new YouTubeResumableTransfer)->forceFill([
            'uuid' => (string) Str::uuid(), 'organisation_id' => $org->id, 'brand_id' => $brand->id,
            'media_publication_run_id' => $run->id, 'media_asset_id' => $asset->id,
            'armed_by_user_id' => $actor->id, 'state' => 'HELD', 'next_action' => 'INIT',
            'total_bytes' => (int) $asset->byte_size, 'canonical_sha256' => strtolower((string) $asset->sha256),
            'canonical_mime' => strtolower((string) $asset->mime_type), 'committed_bytes' => 0,
            'next_check_at' => null, 'version' => 1, 'sequence' => 0, 'poll_count' => 0,
            'blocker_code' => 'YOUTUBE_UPLOAD_RELEASE_DISABLED',
        ]);
        $transfer->save();
        $this->event($transfer, 'TRANSFER_CREATED', null, 'HELD', ['remote_sent' => false]);

        return $transfer;
    }

    public function arm(YouTubeResumableTransfer $row, User $actor, int $version): YouTubeResumableTransfer
    {
        if ($row->state !== 'HELD' || (int) $row->version !== $version || $row->lease_owner !== null
            || $row->attempts()->exists()) {
            throw new ConflictHttpException('YouTube transfer version/state changed or was previously attempted.');
        }
        $parent = MediaPublicationRun::query()->where('organisation_id', $row->organisation_id)
            ->where('brand_id', $row->brand_id)->whereKey($row->media_publication_run_id)
            ->lockForUpdate()->firstOrFail();
        if ($parent->state !== 'HELD' || $parent->provider !== 'youtube') {
            throw new ConflictHttpException('Parent media run is no longer eligible for arming.');
        }
        $this->publicationRuns->arm($parent, $actor, (int) $parent->version);
        $row->forceFill(['state' => 'READY', 'next_check_at' => now(), 'armed_by_user_id' => $actor->id,
            'blocker_code' => null, 'version' => $row->version + 1])->save();
        $this->event($row, 'OPERATOR_ARMED', 'HELD', 'READY', ['remote_sent' => false]);

        return $row;
    }

    public function dueIds(int $limit): array
    {
        return YouTubeResumableTransfer::query()->whereIn('state', ['READY', 'NEXT_CHUNK', 'NEEDS_PROBE', 'VERIFYING'])
            ->where('next_check_at', '<=', now())->whereNull('lease_owner')->orderBy('id')
            ->limit(max(1, min(50, $limit)))->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function claim(int $id, string $owner): ?YouTubeResumableTransfer
    {
        return DB::transaction(function () use ($id, $owner) {
            $row = YouTubeResumableTransfer::query()->whereKey($id)->lockForUpdate()->first();
            if (! $row || ! in_array($row->state, ['READY', 'NEXT_CHUNK', 'NEEDS_PROBE', 'VERIFYING'], true)
                || $row->lease_owner !== null || ! $row->next_check_at || $row->next_check_at->isFuture()) {
                return null;
            }
            $old = $row->state;
            $row->forceFill(['state' => 'LEASED', 'lease_owner' => $owner, 'lease_until' => now()->addMinutes(3),
                'version' => $row->version + 1])->save();
            $this->event($row, 'WORKER_CLAIMED', $old, 'LEASED', ['remote_sent' => false]);

            return $row;
        }, 3);
    }

    public function hold(int $id, string $owner, string $code): bool
    {
        return DB::transaction(function () use ($id, $owner, $code) {
            $row = $this->leased($id, $owner, 'LEASED');
            if (! $row) {
                return false;
            }
            $safe = $this->safe($code);
            $row->forceFill(['state' => 'HELD', 'blocker_code' => $safe, 'lease_owner' => null, 'lease_until' => null,
                'next_check_at' => null, 'version' => $row->version + 1])->save();
            $this->event($row, 'PREFLIGHT_HELD', 'LEASED', 'HELD', ['code' => $safe]);

            return true;
        }, 3);
    }

    public function start(int $id, string $owner): ?array
    {
        return DB::transaction(function () use ($id, $owner) {
            $row = $this->leased($id, $owner, 'LEASED');
            if (! $row) {
                return null;
            }
            $action = $row->next_action;
            if (! in_array($action, ['INIT', 'PROBE', 'CHUNK', 'VERIFY'], true)) {
                return null;
            }
            if ($action === 'INIT' && $row->attempts()->where('action', 'INIT')->exists()) {
                return null;
            }
            if ($row->attempts()->where('state', 'STARTED')->exists()) {
                return null;
            }
            if (in_array($action, ['PROBE', 'VERIFY'], true) && $row->poll_count >= 20) {
                return null;
            }
            $sequence = $row->sequence + 1;
            $attempt = (new YouTubeResumableAttempt)->forceFill([
                'organisation_id' => $row->organisation_id, 'brand_id' => $row->brand_id,
                'youtube_resumable_transfer_id' => $row->id, 'sequence' => $sequence,
                'action' => $action, 'offset_bytes' => $row->committed_bytes, 'state' => 'STARTED',
            ]);
            $attempt->save();
            $row->forceFill(['state' => 'DISPATCHING', 'sequence' => $sequence,
                'poll_count' => in_array($action, ['PROBE', 'VERIFY'], true) ? $row->poll_count + 1 : $row->poll_count,
                'version' => $row->version + 1])->save();
            $this->event($row, 'HTTP_STAGE_STARTED', 'LEASED', 'DISPATCHING', [
                'action' => $action, 'sequence' => $sequence, 'remote_may_receive_request' => true]);

            return ['attempt_id' => (int) $attempt->id, 'action' => $action, 'offset' => (int) $row->committed_bytes];
        }, 3);
    }

    public function finish(int $id, string $owner, int $attemptId, array $outcome): bool
    {
        return DB::transaction(function () use ($id, $owner, $attemptId, $outcome) {
            $row = $this->leased($id, $owner, 'DISPATCHING');
            if (! $row) {
                return false;
            }
            $attempt = YouTubeResumableAttempt::query()->where('organisation_id', $row->organisation_id)
                ->where('brand_id', $row->brand_id)->where('youtube_resumable_transfer_id', $row->id)
                ->whereKey($attemptId)->where('state', 'STARTED')->where('sequence', $row->sequence)
                ->lockForUpdate()->first();
            if (! $attempt) {
                return false;
            }
            $result = (string) ($outcome['state'] ?? 'UNCERTAIN');
            $action = $attempt->action;
            $next = 'UNCERTAIN';
            $op = $action;
            $extra = [];
            $code = $this->safe($outcome['code'] ?? 'GOOGLE_TRANSFER_UNCERTAIN');
            if ($action === 'INIT' && $result === 'SESSION_CREATED') {
                try {
                    Protocol::assertSessionUrl((string) ($outcome['session_url'] ?? ''));
                    $extra['session_url_ciphertext'] = (string) $outcome['session_url'];
                    $next = 'NEEDS_PROBE';
                    $op = 'PROBE';
                    $code = 'GOOGLE_SESSION_CREATED';
                } catch (\DomainException) {
                    $code = 'INVALID_GOOGLE_SESSION_URL';
                }
            } elseif (in_array($action, ['PROBE', 'CHUNK'], true) && $result === 'OFFSET_CONFIRMED') {
                $newOffset = $outcome['offset'] ?? null;
                if (is_int($newOffset) && $newOffset >= $row->committed_bytes && $newOffset < $row->total_bytes) {
                    $extra['committed_bytes'] = $newOffset;
                    $next = $action === 'PROBE' ? 'NEXT_CHUNK' : 'NEEDS_PROBE';
                    $op = $action === 'PROBE' ? 'CHUNK' : 'PROBE';
                    $code = 'GOOGLE_OFFSET_CONFIRMED';
                } else {
                    $code = 'GOOGLE_OFFSET_ROLLBACK_OR_INVALID';
                }
            } elseif (in_array($action, ['CHUNK', 'PROBE'], true) && $result === 'UPLOADED') {
                $id = (string) ($outcome['video_id'] ?? '');
                if (preg_match('/^[A-Za-z0-9_-]{6,64}$/D', $id)) {
                    $extra['video_id_ciphertext'] = $id;
                    $extra['committed_bytes'] = $row->total_bytes;
                    $next = 'VERIFYING';
                    $op = 'VERIFY';
                    $code = 'VIDEO_UPLOAD_AWAITING_VERIFICATION';
                } else {
                    $code = 'VIDEO_RECEIPT_INVALID';
                }
            } elseif ($action === 'VERIFY' && $result === 'PROCESSING') {
                if ($row->poll_count < 20) {
                    $next = 'VERIFYING';
                    $op = 'VERIFY';
                    $code = 'VIDEO_PROCESSING';
                } else {
                    $next = 'MANUAL_REQUIRED';
                    $code = 'VIDEO_PROCESSING_POLL_LIMIT';
                }
            } elseif ($action === 'VERIFY' && $result === 'VERIFIED') {
                $video = (string) $row->video_id_ciphertext;
                $channel = (string) ($outcome['owner_id'] ?? '');
                if ($video !== '' && hash_equals($video, (string) ($outcome['video_id'] ?? ''))) {
                    $problem = $this->publicationRuns->completeVerifiedYouTube((int) $row->media_publication_run_id, $video, $channel);
                    if ($problem === null) {
                        $next = 'COMPLETED';
                        $op = 'COMPLETE';
                        $code = 'YOUTUBE_POST_VERIFIED';
                    } else {
                        $code = $this->safe($problem);
                    }
                } else {
                    $code = 'VIDEO_VERIFICATION_MISMATCH';
                }
            } elseif ($action === 'CHUNK' && $result === 'NEEDS_PROBE') {
                $next = 'NEEDS_PROBE';
                $op = 'PROBE';
                $code = 'GOOGLE_CHUNK_REQUIRES_PROBE';
            } elseif ($action === 'PROBE' && $result === 'NEEDS_PROBE' && $row->poll_count < 20) {
                $next = 'NEEDS_PROBE';
                $op = 'PROBE';
                $code = 'GOOGLE_PROBE_DEFERRED';
            } elseif (in_array($result, ['FAILED', 'MANUAL_REQUIRED'], true)) {
                $next = 'MANUAL_REQUIRED';
            }
            $attempt->forceFill(['state' => $next, 'outcome_code' => $code, 'finished_at' => now()])->save();
            $row->forceFill(array_merge($extra, ['state' => $next, 'next_action' => $op,
                'lease_owner' => null, 'lease_until' => null, 'version' => $row->version + 1, 'blocker_code' => $code,
                'next_check_at' => in_array($next, ['NEXT_CHUNK', 'NEEDS_PROBE', 'VERIFYING'], true)
                    ? now()->addSeconds($next === 'NEXT_CHUNK' ? 1 : 60) : null]))->save();
            if (in_array($next, ['UNCERTAIN', 'MANUAL_REQUIRED'], true)) {
                $this->publicationRuns->quarantineYouTube((int) $row->media_publication_run_id, $code);
            }
            $this->event($row, 'HTTP_STAGE_OUTCOME', 'DISPATCHING', $next, [
                'action' => $action, 'code' => $code, 'sequence' => $attempt->sequence,
                'offset_confirmed' => $next === 'NEXT_CHUNK', 'receipt_verified' => $next === 'COMPLETED']);

            return true;
        }, 3);
    }

    public function recover(int $limit): array
    {
        $count = 0;
        foreach (YouTubeResumableTransfer::query()->whereIn('state', ['LEASED', 'DISPATCHING'])
            ->where('lease_until', '<', now())->limit(max(1, min(100, $limit)))->pluck('id') as $id) {
            DB::transaction(function () use ($id, &$count) {
                $row = YouTubeResumableTransfer::query()->whereKey($id)->lockForUpdate()->first();
                if (! $row || ! in_array($row->state, ['LEASED', 'DISPATCHING'], true)
                    || ! $row->lease_until || $row->lease_until->isFuture()) {
                    return;
                }
                $old = $row->state;
                $sent = $old === 'DISPATCHING';
                $row->forceFill(['state' => $sent ? 'UNCERTAIN' : 'HELD',
                    'blocker_code' => $sent ? 'UPLOAD_LEASE_EXPIRED_AFTER_SEND' : 'UPLOAD_LEASE_EXPIRED_BEFORE_SEND',
                    'next_check_at' => null, 'lease_owner' => null, 'lease_until' => null, 'version' => $row->version + 1])->save();
                if ($sent) {
                    $this->publicationRuns->quarantineYouTube((int) $row->media_publication_run_id,
                        'UPLOAD_LEASE_EXPIRED_AFTER_SEND');
                }
                $this->event($row, 'LEASE_RECOVERY', $old, $row->state, ['remote_may_have_received_request' => $sent]);
                $count++;
            }, 3);
        }

        return ['quarantined_or_held' => $count];
    }

    private function leased(int $id, string $owner, string $state): ?YouTubeResumableTransfer
    {
        return YouTubeResumableTransfer::query()->whereKey($id)->where('state', $state)
            ->where('lease_owner', $owner)->where('lease_until', '>', now())->lockForUpdate()->first();
    }

    private function event(YouTubeResumableTransfer $row,string $action,?string $from,string $to,array $safe): void
    {
        (new YouTubeResumableEvent)->forceFill([
            'organisation_id' => $row->organisation_id, 'brand_id' => $row->brand_id,
            'youtube_resumable_transfer_id' => $row->id, 'action' => $action,
            'from_state' => $from, 'to_state' => $to, 'safe_details' => $safe,
        ])->save();
    }

    private function safe(string $code): string
    {
        return preg_match('/^[A-Z0-9_]{2,70}$/D',$code) ? $code : 'YOUTUBE_EXECUTION_UNKNOWN';
    }
}
