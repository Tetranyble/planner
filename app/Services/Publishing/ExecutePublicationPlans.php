<?php

namespace App\Services\Publishing;

use App\Authorization\BrandPermissionGuard;
use App\Authorization\CurrentOrganisation;
use App\Domain\Publishing\Delivery\DispatchOutcome;
use App\Domain\Publishing\LiveContracts\GuardedTextDispatch;
use App\Domain\Publishing\LiveContracts\LaravelTextHttpTransport;
use App\Domain\Publishing\LiveContracts\PreparedProviderRequest;
use App\Domain\Publishing\LiveContracts\PublishingScopePolicy;
use App\Domain\Publishing\LiveContracts\TextDispatchAuthorization;
use App\Domain\Publishing\LiveContracts\TextPostRequestCompiler;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\PublicationJob;
use App\Models\User;
use App\Repository\Contracts\PublicationDeliveryContextRepositoryContract;
use App\Repository\Contracts\PublicationExecutionRepositoryContract;
use App\Repository\Contracts\PublicationIntentRepositoryContract;
use App\Services\Content\AssessPublicationReadiness;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** Explicit manager arm + lease-fenced worker. Nothing can send with kill switch off. */
final class ExecutePublicationPlans
{
    public function __construct(
        private readonly BrandPermissionGuard $guard,
        private readonly CurrentOrganisation $currentOrganisation,
        private readonly PublicationIntentRepositoryContract $plans,
        private readonly PublicationExecutionRepositoryContract $execution,
        private readonly PublicationDeliveryContextRepositoryContract $delivery,
        private readonly AssessPublicationReadiness $readiness,
        private readonly MetaPageTokenResolver $pageTokens,
        private readonly VerifySocialPublishingCapability $capabilities,
    ) {}

    public function arm(User $actor, Organisation $organisation, Brand $brand, int $jobId, int $expectedVersion): array
    {
        if ((int) $brand->organisation_id !== (int) $organisation->id || ! $this->guard->allows($actor, $brand, 'approval_request.update')) {
            throw new AuthorizationException('Publishing authorization denied.');
        }
        if (! self::isEnabled()) {
            throw new ConflictHttpException('Live text publishing is disabled by deployment policy.');
        }

        return DB::transaction(function () use ($actor, $organisation, $brand, $jobId, $expectedVersion) {
            Organisation::query()->whereKey($organisation->id)->lockForUpdate()->firstOrFail();
            $lockedBrand = Brand::query()->where('organisation_id', $organisation->id)->whereKey($brand->id)->lockForUpdate()->firstOrFail();
            if (! $this->guard->allows($actor, $lockedBrand, 'approval_request.update')) {
                throw new AuthorizationException('Publishing permission has changed.');
            }
            $job = $this->plans->findLocked($organisation, $lockedBrand, $jobId);
            // Reject media-target jobs before invoking any canonical Storage inspection in a DB lock.
            $manifest = $this->delivery->manifest($organisation, $lockedBrand, $job);
            if ($manifest['has_media']) {
                throw new ConflictHttpException('Only text-only publication jobs can be armed.');
            }
            $context = $this->plans->context($organisation, $lockedBrand, $job);
            $assessment = $this->readiness->assess($actor, $organisation, $lockedBrand,
                $context['item_uuid'], $context['target_uuid'], (int) $job->approval_request_id);
            // Legacy diagnostic always includes the engine-not-implemented blocker; the
            // operational gate substitutes actual opt-in + current verification.
            if (array_diff($assessment['blockers'], ['PUBLISHING_ENGINE_NOT_IMPLEMENTED']) !== []) {
                throw new ConflictHttpException('Publication approvals, account permissions or media are not ready.');
            }
            if ($manifest['mode'] !== 'AUTOMATIC' || $manifest['has_media'] || ! $manifest['account_active']
                || ! in_array($manifest['provider'], ['facebook', 'linkedin', 'x'], true)) {
                throw new ConflictHttpException('Only verified Facebook Page, LinkedIn member, or X automatic text-only targets can be armed.');
            }
            $armed = $this->execution->arm($organisation, $lockedBrand, $job, (int) $actor->id, $expectedVersion);

            return ['job_id' => (int) $armed->id, 'state' => $armed->state, 'run_at' => $armed->run_at->toIso8601String(),
                'version' => (int) $armed->version, 'requires_worker' => true];
        }, 3);
    }

    /** @return array{claimed:int,sent:int,blocked:int,uncertain:int,disabled:bool} */
    public function process(int $limit = 20): array
    {
        $counters = ['claimed' => 0, 'sent' => 0, 'blocked' => 0, 'uncertain' => 0, 'disabled' => ! self::isEnabled()];
        if (! self::isEnabled()) {
            return $counters;
        }
        foreach ($this->execution->dueIds(max(1, min(100, $limit))) as $jobId) {
            $owner = bin2hex(random_bytes(24));
            $claim = $this->execution->claim($jobId, $owner);
            if (! $claim) {
                continue;
            }
            $counters['claimed']++;
            $operation = null;
            try {
                $scope = $this->execution->scopedLease($jobId, $owner);
                if (! $scope) {
                    continue;
                }
                $actor = User::query()->whereKey($claim['armed_by_user_id'])->first();
                if (! $actor) {
                    $this->execution->holdBlocked($jobId, $owner, 'ARMING_USER_UNAVAILABLE');
                    $counters['blocked']++;

                    continue;
                }
                // Queue entry points have no HTTP identity. Restore verified organisation context
                // and enforce current Kinship/brand permissions, not persisted booleans.
                $ready = $this->currentOrganisation->within($actor, (int) $scope['organisation']->id,
                    fn () => $this->prepare($actor, $scope['organisation'], $scope['brand'], $scope['job']));
                if (isset($ready['blocked'])) {
                    $this->execution->holdBlocked($jobId, $owner, $ready['blocked']);
                    $counters['blocked']++;

                    continue;
                }
                // Resolve the credential BEFORE an attempt starts. Failed credential access
                // cannot create ambiguous remote-send evidence.
                $token = $this->delivery->accessToken($scope['organisation'], $scope['brand'], $scope['job']);
                if ($ready['provider'] === 'facebook') {
                    // Read-only Meta Page-token exchange occurs BEFORE a STARTED attempt.
                    $token = $this->pageTokens->resolve($token, $ready['page_id']);
                }
                $operation = $this->execution->startAttempt($jobId, $owner);
                if ($operation === null) {
                    $counters['uncertain']++;

                    continue;
                }
                // These facts are sourced from the current permission and approval checks
                // and the persisted STARTED attempt, never from an HTTP caller or DTO.
                $fenced = $this->execution->attemptIsFenced($jobId, $owner, $operation);
                $released = self::isEnabled();
                $proof = new TextDispatchAuthorization(
                    $released,
                    $released && $ready['provider_permitted'],
                    $ready['account_verified'],
                    $ready['credential_healthy'],
                    $ready['approval_current'],
                    $ready['media_verified'],
                    $fenced,
                    $fenced,
                    $fenced && $scope['job']->run_at->lessThanOrEqualTo(now()),
                    $fenced && (int) $scope['job']->attempt_count === 0,
                );
                $outcome = (new GuardedTextDispatch(new LaravelTextHttpTransport))
                    ->execute($ready['request'], $proof, $token);
                if ($this->execution->finishAttempt($jobId, $owner, $operation, $outcome)) {
                    if ($outcome->kind === 'SUCCESS') {
                        $counters['sent']++;
                    } elseif ($outcome->kind === 'UNCERTAIN' || $outcome->kind === 'RETRYABLE' || $outcome->kind === 'RATE_LIMITED') {
                        $counters['uncertain']++;
                    }
                }
            } catch (\Throwable) {
                // Never reveal exceptions or credentials. A crash after STARTED may have
                // reached the remote platform. Such attempts must be quarantined.
                if ($operation !== null) {
                    $this->execution->finishAttempt($jobId, $owner, $operation, new DispatchOutcome('UNCERTAIN', 'EXECUTION_EXCEPTION'));
                    $counters['uncertain']++;
                } else {
                    $this->execution->holdBlocked($jobId, $owner, 'EXECUTION_PREFLIGHT_FAILED');
                    $counters['blocked']++;
                }
            }
        }

        return $counters;
    }

    /** @return array{request?:PreparedProviderRequest,blocked?:string} */
    private function prepare(User $actor, Organisation $organisation, Brand $brand, PublicationJob $job): array
    {
        if (! $this->guard->allows($actor, $brand, 'approval_request.update') || ! $this->guard->allows($actor, $brand, 'approval_request.index')) {
            return ['blocked' => 'ARMING_PERMISSION_REVOKED'];
        }
        if (! self::isEnabled()) {
            return ['blocked' => 'PUBLISHING_DISABLED'];
        }
        $manifest = $this->delivery->manifest($organisation, $brand, $job);
        if ($manifest['has_media']) {
            return ['blocked' => 'MEDIA_ADAPTER_UNAVAILABLE'];
        }
        try {
            // Refresh provider identity/scope evidence at execution time, not merely when armed.
            // A failed read-only probe produces a manual hold without a STARTED attempt.
            $evidence = $this->capabilities->verify($actor, $organisation, $brand, $manifest['account_uuid']);
            if ($evidence['mode'] !== 'AUTOMATIC') {
                return ['blocked' => 'PROVIDER_CAPABILITY_NOT_VERIFIED'];
            }
        } catch (\Throwable) {
            return ['blocked' => 'PROVIDER_CAPABILITY_PROBE_FAILED'];
        }
        $context = $this->plans->context($organisation, $brand, $job);
        $assessment = $this->readiness->assess($actor, $organisation, $brand,
            $context['item_uuid'], $context['target_uuid'], (int) $job->approval_request_id);
        if (array_diff($assessment['blockers'], ['PUBLISHING_ENGINE_NOT_IMPLEMENTED']) !== []) {
            return ['blocked' => 'READINESS_INVALIDATED'];
        }
        if ($manifest['mode'] !== 'AUTOMATIC' || $manifest['has_media'] || ! $manifest['account_active']
            || ! in_array($manifest['provider'], ['facebook', 'linkedin', 'x'], true)) {
            return ['blocked' => 'PROVIDER_TARGET_INELIGIBLE'];
        }
        try {
            $ref = PublishingScopePolicy::authorRef($manifest['provider'], $manifest['account_kind'], $manifest['external_account_id']);
            $request = TextPostRequestCompiler::compile($manifest['provider'], $ref, $manifest['caption'],
                (string) config('social_integrations.linkedin_api_version', '202610'));
        } catch (\Throwable) {
            return ['blocked' => 'INVALID_PROVIDER_TEXT_CONTRACT'];
        }

        return ['request' => $request, 'provider' => $manifest['provider'], 'page_id' => $manifest['external_account_id'],
            'provider_permitted' => true, 'account_verified' => true,
            'credential_healthy' => true, 'approval_current' => true, 'media_verified' => true];
    }

    public static function isEnabled(): bool
    {
        return ! (bool) config('planner.emergency_publish_stop',true)
            && (bool) config('planner.text_dispatch_enabled',false)
            && (bool) config('planner.text_dispatch_provider_approved',false)
            && app()->environment('production')
            && (bool) config('planner.text_dispatch_released',false);
    }
}
