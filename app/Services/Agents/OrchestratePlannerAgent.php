<?php

namespace App\Services\Agents;

use App\Authorization\BrandPermissionGuard;
use App\Domain\Agent\AgentDelegationPolicy;
use App\Domain\Agent\AgentDelegationPolicy as Policy;
use App\Models\Brand;
use App\Models\ContentTarget;
use App\Models\Organisation;
use App\Models\PlannerAgentDelegation;
use App\Models\PlannerAgentWorkOrder;
use App\Models\User;
use App\Repository\Contracts\AuditEventRepositoryContract;
use App\Repository\Contracts\BrandMemberRepositoryContract;
use App\Repository\Contracts\ContentApprovalRepositoryContract;
use App\Repository\Contracts\ContentStudioRepositoryContract;
use App\Repository\Contracts\PlannerAgentAutonomyRepositoryContract;
use App\Repository\Contracts\PlannerAgentRepositoryContract;
use App\Repository\Contracts\PublicationDeliveryContextRepositoryContract;
use App\Repository\Contracts\PublicationIntentRepositoryContract;
use App\Services\Content\ManageContentApprovals;
use App\Services\Content\ManageContentStudio;
use App\Services\Content\ManagePublicationIntents;
use App\Services\Publishing\ExecutePublicationPlans;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** An external agent proposes steps; Planner enforces all existing domain policies per step. */
final class OrchestratePlannerAgent
{
    public function __construct(
        private readonly PlannerAgentRepositoryContract $agents,
        private readonly PlannerAgentAutonomyRepositoryContract $autonomy,
        private readonly BrandMemberRepositoryContract $members,
        private readonly BrandPermissionGuard $guard,
        private readonly AuditEventRepositoryContract $audit,
        private readonly ManageContentStudio $studio,
        private readonly ContentStudioRepositoryContract $studioRepo,
        private readonly ContentApprovalRepositoryContract $approvalRepo,
        private readonly ManageContentApprovals $approvals,
        private readonly ManagePublicationIntents $plans,
        private readonly PublicationIntentRepositoryContract $planRepo,
        private readonly PublicationDeliveryContextRepositoryContract $delivery,
        private readonly ExecutePublicationPlans $executor,
    ) {}

    /** Caller must already be authenticated as a Sanctum planner:agent principal. */
    public function execute(User $actor, array $scope, string $action, string $key, array $input): array
    {
        if (! Policy::permits((string) ($scope['delegation']->mode ?? ''), $action)) {
            throw new AuthorizationException('This delegation cannot perform the selected action.');
        }
        if (! preg_match('/\A[a-zA-Z0-9:_-]{12,96}\z/D', $key)) {
            throw ValidationException::withMessages(['idempotency_key' => 'Provide a unique 12-96 character safe idempotency key.']);
        }
        $payload = $this->validatePayload($action, $input);
        $hash = Policy::canonicalHash($action, $payload);
        $org = $scope['organisation'];
        $brand = $scope['brand'];
        $delegationId = (int) $scope['delegation']->id;
        if (! $actor->exists || (int) $brand->organisation_id !== (int) $org->id) {
            throw new AuthorizationException('Invalid active agent principal or brand.');
        }

        return DB::transaction(function () use ($actor, $org, $brand, $delegationId, $action, $key, $payload, $hash): array {
            // Match Content Studio lock order: organisation -> brand -> delegation -> order.
            Organisation::query()->whereKey($org->id)->lockForUpdate()->firstOrFail();
            $lockedBrand = Brand::query()->where('organisation_id', $org->id)->whereKey($brand->id)->lockForUpdate()->firstOrFail();
            $grant = $this->agents->lockedForAction($org, $lockedBrand, $actor, $delegationId);
            if (! Policy::permits($grant->mode, $action)) {
                throw new AuthorizationException('Agent capability was revoked.');
            }
            if (! config('planner.agent_access_enabled', false) || ! config('planner.agent_write_enabled', false)) {
                throw new AuthorizationException('Agent orchestration is disabled by deployment policy.');
            }
            $current = $this->agents->receipt($grant, $key);
            if ($current) {
                if (! hash_equals($current->input_hash, $hash)) {
                    throw new ConflictHttpException('Idempotency key used with different action details.');
                }

                return ['replayed' => true, 'action' => $action, 'result' => $current->result];
            }
            if ($this->agents->dailyCount($grant) >= (int) $grant->daily_action_limit) {
                throw new ConflictHttpException('Agent daily action allowance exhausted.');
            }
            if ($action === 'publication.arm') {
                if (! config('planner.agent_delegated_publishing_enabled', false) || $grant->mode !== Policy::DELEGATED_PUBLISH) {
                    throw new AuthorizationException('Delegated publication is disabled.');
                }
                if ($this->agents->dailyCount($grant, 'publication.arm') >= (int) $grant->daily_arm_limit) {
                    throw new ConflictHttpException('Daily delegated publication limit exhausted.');
                }
            }
            $result = $this->apply($actor, $org, $lockedBrand, $grant, $action, $payload);
            $this->agents->storeReceipt($grant, $action, $key, $hash, $result);
            $this->audit->record($org, $actor, 'agent.action.'.$action, 'planner_agent_delegation', (string) $grant->id, $lockedBrand,
                ['idempotency_hash' => hash('sha256', $key), 'agent_action' => $action, 'workflow_uuid' => $payload['work_order_uuid'] ?? ($result['work_order_uuid'] ?? null)]);

            return ['replayed' => false, 'action' => $action, 'result' => $result];
        }, 3);
    }

    private function permission(User $actor, Brand $brand, string $permission): void
    {
        if (! $this->guard->allows($actor, $brand, $permission)) {
            throw new AuthorizationException('The agent owner no longer has the required brand permission: '.$permission);
        }
    }

    private function order(Organisation $org, Brand $brand, PlannerAgentDelegation $grant, string $uuid): PlannerAgentWorkOrder
    {
        $order = $this->agents->order($org, $brand, $uuid, true);
        if ((int) $order->delegation_id !== (int) $grant->id) {
            throw new AuthorizationException('Agent work orders cannot be shared across delegations.');
        }

        return $order;
    }

    private function allowedAccount(PlannerAgentDelegation $grant, string $uuid): void
    {
        if (! in_array($uuid, $grant->account_uuids ?? [], true)) {
            throw new AuthorizationException('Social account is not on the delegated allowlist.');
        }
    }

    private function targetForOrder(Organisation $org, Brand $brand, PlannerAgentWorkOrder $order, string $targetUuid): ContentTarget
    {
        if (! $order->draft_content_uuid) {
            throw new ConflictHttpException('Record a draft before requesting social review.');
        }
        $item = $this->studioRepo->findItem($org, $brand, $order->draft_content_uuid);

        return $this->approvalRepo->target($org, $brand, $item, $targetUuid);
    }

    private function apply(User $actor, Organisation $org, Brand $brand, PlannerAgentDelegation $grant, string $action, array $data): array
    {
        if ($action === 'research.autorun') {
            $this->permission($actor, $brand, 'content_item.store');
            if (! config('planner.agent_autonomy_enabled', false)) {
                throw new AuthorizationException('Autonomous research is disabled by deployment policy.');
            }
            $run = $this->autonomy->queue($org, $brand, $actor, $grant, $data);

            return ['run_uuid' => $run->uuid, 'status' => $run->status,
                'next_action' => 'poll autonomous runs; then resource.promote',
                'human_approval_required' => true, 'network_request_started' => false];
        }
        if ($action === 'research.cancel') {
            $this->permission($actor, $brand, 'content_item.store');
            $run = $this->autonomy->find($org, $brand, $grant, $data['run_uuid'], true);
            $cancelled = $this->autonomy->cancel($org, $brand, $run);

            return ['run_uuid' => $run->uuid, 'cancelled' => $cancelled, 'in_flight_requests_not_interrupted' => true];
        }
        if ($action === 'resource.promote') {
            $this->permission($actor, $brand, 'content_item.store');
            $run = $this->autonomy->find($org, $brand, $grant, $data['run_uuid'], true);
            if ($run->status === 'DRAFT_CREATED') {
                return ['run_uuid' => $run->uuid, 'work_order_uuid' => $run->work_order_uuid,
                    'content_uuid' => $run->content_uuid, 'replayed' => true, 'requires_human_approval' => true];
            }
            if ($run->status !== 'READY_FOR_REVIEW' || ! $run->resource_text || ! $run->findings
              || ! $run->resource_hash || ! $run->research_hash || ! is_array($run->sources)) {
                throw new ConflictHttpException('Research and generated resource must be complete before promotion.');
            }
            if (! hash_equals($run->resource_hash, hash('sha256', $run->resource_text))) {
                throw new ConflictHttpException('Resource integrity mismatch.');
            }
            if (! hash_equals($run->research_hash, AgentDelegationPolicy::canonicalHash('research.evidence', [
                'findings' => $run->findings, 'sources' => $run->sources]))) {
                throw new ConflictHttpException('Research provenance integrity mismatch.');
            }
            $research = $this->apply($actor, $org, $brand, $grant, 'research.capture', [
                'title' => $run->title, 'objective' => $run->objective, 'findings' => $run->findings, 'sources' => $run->sources]);
            $draft = $this->apply($actor, $org, $brand, $grant, 'content.draft', [
                'work_order_uuid' => $research['work_order_uuid'], 'title' => $run->title,
                'copy_text' => $run->resource_text,
                'content_kind' => $run->resource_kind === 'CAMPAIGN_BRIEF' ? 'ARTICLE' : 'TEXT', 'media_uuids' => []]);
            $this->autonomy->promoted($run, $research['work_order_uuid'], $draft['draft']['uuid']);

            return ['run_uuid' => $run->uuid, 'work_order_uuid' => $research['work_order_uuid'],
                'draft' => $draft['draft'], 'requires_human_approval' => true, 'published' => false];
        }
        if ($action === 'research.capture') {
            $this->permission($actor, $brand, 'content_item.store');
            $sources = Policy::sources($data['sources'] ?? []);
            $researchHash = Policy::canonicalHash('research.capture', [
                'title' => $data['title'], 'objective' => $data['objective'], 'findings' => $data['findings'], 'sources' => $sources]);
            $order = $this->agents->createOrder($org, $brand, $actor, $grant, [...$data, 'sources' => $sources, 'research_hash' => $researchHash]);

            return ['work_order_uuid' => $order->uuid, 'status' => 'RESEARCH_RECORDED',
                'research_hash' => $researchHash, 'sources_verified' => false,
                'next_action' => 'content.draft', 'warning' => 'Agent-reported findings are not fact-checked.'];
        }
        if ($action === 'media.link') {
            $this->permission($actor, $brand, 'media_asset.store');

            return $this->studio->linkMedia($actor, $org, $brand, (int) $data['storage_media_id']);
        }
        $order = $this->order($org, $brand, $grant, $data['work_order_uuid']);
        if ($action === 'content.draft') {
            $this->permission($actor, $brand, 'content_item.store');
            if ($order->draft_content_uuid !== null) {
                throw new ConflictHttpException('A draft is already linked. Create a new work order for a new draft.');
            }
            $draft = $this->studio->create($actor, $org, $brand, [
                'title' => $data['title'], 'copy_text' => $data['copy_text'], 'content_kind' => $data['content_kind'],
                'media_uuids' => $data['media_uuids'] ?? [], 'ai_generated' => true, 'includes_sensitive_claim' => null,
            ]);
            $this->agents->updateOrder($order, ['draft_content_uuid' => $draft['uuid'], 'status' => 'DRAFT_CREATED'], 'content.draft',
                ['draft_uuid' => $draft['uuid'], 'version_uuid' => $draft['version_uuid'], 'research_hash' => $order->research_hash]);

            return ['work_order_uuid' => $order->uuid, 'draft' => $draft, 'requires_human_approval' => true];
        }
        if ($action === 'content.target') {
            $this->permission($actor, $brand, 'content_target.store');
            if (! $order->draft_content_uuid) {
                throw new ConflictHttpException('The work order has no draft. Create one first.');
            }
            $this->allowedAccount($grant, $data['social_account_uuid']);
            if ($data['publishing_mode'] === 'AUTOMATIC' && $grant->mode !== Policy::DELEGATED_PUBLISH) {
                throw new AuthorizationException('Automatic target creation requires owner-delegated publishing.');
            }
            $target = $this->studio->target($actor, $org, $brand, (string) $order->draft_content_uuid, [
                'version_uuid' => $data['version_uuid'], 'social_account_uuid' => $data['social_account_uuid'],
                'platform_caption' => $data['platform_caption'], 'publishing_mode' => $data['publishing_mode']]);
            $this->agents->updateOrder($order, ['status' => 'TARGET_CREATED'], 'content.target',
                ['target_uuid' => $target['uuid'], 'account_uuid' => $data['social_account_uuid'], 'mode' => $data['publishing_mode']]);

            return ['work_order_uuid' => $order->uuid, 'target' => $target, 'requires_human_approval' => true];
        }
        if ($action === 'approval.request') {
            $this->permission($actor, $brand, 'approval_request.store');
            $target = $this->targetForOrder($org, $brand, $order, $data['target_uuid']);
            $this->allowedAccount($grant, (string) $target->account->uuid);
            $result = $this->approvals->submit($actor, $org, $brand, (string) $order->draft_content_uuid,
                $data['target_uuid'], (string) $target->payload_hash);
            $this->agents->updateOrder($order, ['status' => 'AWAITING_HUMAN_REVIEW'], 'approval.request',
                ['approval_id' => $result['id'], 'target_uuid' => $data['target_uuid']]);

            return ['work_order_uuid' => $order->uuid, 'approval' => $result, 'agent_can_approve' => false];
        }
        if ($action === 'publication.plan') {
            $this->permission($actor, $brand, 'approval_request.store');
            $target = $this->targetForOrder($org, $brand, $order, $data['target_uuid']);
            $this->allowedAccount($grant, (string) $target->account->uuid);
            $planned = $this->plans->create($actor, $org, $brand, (string) $order->draft_content_uuid,
                $data['target_uuid'], (int) $data['approval_id'], $data['occurrence_key'], $data['scheduled_at']);
            $this->agents->updateOrder($order, ['status' => 'HELD_FOR_RELEASE'], 'publication.plan',
                ['publication_job_id' => $planned['id'], 'target_uuid' => $data['target_uuid'], 'approval_id' => $data['approval_id']]);

            return ['work_order_uuid' => $order->uuid, 'plan' => $planned, 'automatically_armed' => false];
        }
        if ($action === 'publication.arm') {
            $this->permission($actor, $brand, 'approval_request.update');
            if ($grant->mode !== Policy::DELEGATED_PUBLISH) {
                throw new AuthorizationException('Review-only agent cannot arm publication.');
            }
            // Delegation was issued by an owner; it becomes unusable for publish arming if
            // that owner loses ownership, even when they retain manager permissions.
            if ($this->members->organisationMember($org, (int) $actor->id)?->role_name !== 'owner') {
                throw new AuthorizationException('Delegated publish requires the granting principal to remain an organisation owner.');
            }
            $job = $this->planRepo->findLocked($org, $brand, (int) $data['job_id']);
            $context = $this->planRepo->context($org, $brand, $job);
            if ((string) $context['item_uuid'] !== (string) $order->draft_content_uuid) {
                throw new AuthorizationException('Publication plan does not belong to this agent work order.');
            }
            $manifest = $this->delivery->manifest($org, $brand, $job);
            $this->allowedAccount($grant, $manifest['account_uuid']);
            $armed = $this->executor->arm($actor, $org, $brand, (int) $job->id, (int) $data['expected_version']);
            $this->agents->updateOrder($order, ['status' => 'ARMED_UNDER_DELEGATION'], 'publication.arm',
                ['publication_job_id' => $armed['job_id'], 'version' => $armed['version']]);

            return ['work_order_uuid' => $order->uuid, 'armed' => $armed, 'approval_still_required' => true,
                'warning' => 'Existing provider and emergency-stop switches still govern execution.'];
        }
        throw new AuthorizationException('Unknown agent action.');
    }

    private function validatePayload(string $action, array $payload): array
    {
        $rules = match ($action) {
            'research.autorun' => ['run_key' => ['required', 'string', 'min:12', 'max:96', 'regex:/\A[a-zA-Z0-9:_-]+\z/D'],
                'title' => ['required', 'string', 'min:3', 'max:180'], 'objective' => ['required', 'string', 'min:5', 'max:3000'],
                'resource_kind' => ['required', Rule::in(['SOCIAL_COPY', 'CAMPAIGN_BRIEF'])]],
            'research.cancel' => ['run_uuid' => ['required', 'uuid']],
            'resource.promote' => ['run_uuid' => ['required', 'uuid']],
            'research.capture' => ['title' => ['required', 'string', 'min:3', 'max:180'], 'objective' => ['required', 'string', 'min:5', 'max:3000'],
                'findings' => ['required', 'string', 'min:5', 'max:12000'], 'sources' => ['present', 'array', 'max:12'],
                'sources.*.url' => ['required', 'url', 'max:1500'], 'sources.*.title' => ['nullable', 'string', 'max:180'],
                'sources.*.note' => ['nullable', 'string', 'max:500']],
            'media.link' => ['storage_media_id' => ['required', 'integer', 'min:1']],
            'content.draft' => ['work_order_uuid' => ['required', 'uuid'], 'title' => ['required', 'string', 'min:2', 'max:180'],
                'copy_text' => ['required', 'string', 'max:50000'], 'content_kind' => ['required', Rule::in(['TEXT', 'IMAGE', 'CAROUSEL', 'VIDEO', 'SHORT', 'ARTICLE'])],
                'media_uuids' => ['sometimes', 'array', 'max:12'], 'media_uuids.*' => ['uuid', 'distinct']],
            'content.target' => ['work_order_uuid' => ['required', 'uuid'], 'version_uuid' => ['required', 'uuid'],
                'social_account_uuid' => ['required', 'uuid'], 'platform_caption' => ['required', 'string', 'max:50000'],
                'publishing_mode' => ['required', Rule::in(['ASSISTED', 'AUTOMATIC'])]],
            'approval.request' => ['work_order_uuid' => ['required', 'uuid'], 'target_uuid' => ['required', 'uuid']],
            'publication.plan' => ['work_order_uuid' => ['required', 'uuid'], 'target_uuid' => ['required', 'uuid'],
                'approval_id' => ['required', 'integer', 'min:1'], 'occurrence_key' => ['required', 'string', 'min:12', 'max:96'],
                'scheduled_at' => ['required', 'string', 'max:50']],
            'publication.arm' => ['work_order_uuid' => ['required', 'uuid'], 'job_id' => ['required', 'integer', 'min:1'],
                'expected_version' => ['required', 'integer', 'min:0']],
            default => throw new AuthorizationException('Unknown agent action.'),
        };

        return Validator::make($payload, $rules)->validate();
    }

    public function listing(User $actor,array $scope): array
    {
        if (! $actor->exists || ! config('planner.agent_access_enabled',false)) {
            throw new AuthorizationException('Agent access denied.');
        }

        // Agent tokens may only inspect their OWN delegation's work orders and runs.
        return ['work_orders' => $this->agents->listOrders($scope['organisation'],$scope['brand'],40,(int) $scope['delegation']->id),
            'autonomous_runs' => $this->autonomy->list($scope['organisation'],$scope['brand'],40,(int) $scope['delegation']->id),
            'mode' => $scope['delegation']->mode,
            'can_arm_approved_publications' => $scope['delegation']->mode === Policy::DELEGATED_PUBLISH
               && (bool) config('planner.agent_delegated_publishing_enabled',false),
            'limits' => ['daily_actions' => $scope['delegation']->daily_action_limit, 'daily_arms' => $scope['delegation']->daily_arm_limit]];
    }
}
