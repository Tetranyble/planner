<?php

namespace App\Services\Agents;

use App\Authorization\BrandPermissionGuard;
use App\Domain\Agent\AgentDelegationPolicy as Policy;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\AuditEventRepositoryContract;
use App\Repository\Contracts\BrandMemberRepositoryContract;
use App\Repository\Contracts\ContentStudioRepositoryContract;
use App\Repository\Contracts\PlannerAgentAutonomyRepositoryContract;
use App\Repository\Contracts\PlannerAgentRepositoryContract;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

final class ManagePlannerAgentDelegations
{
    public function __construct(private readonly BrandPermissionGuard $permissions,
        private readonly BrandMemberRepositoryContract $members,
        private readonly ContentStudioRepositoryContract $studio,
        private readonly PlannerAgentRepositoryContract $repository,
        private readonly PlannerAgentAutonomyRepositoryContract $autonomy,
        private readonly AuditEventRepositoryContract $audit) {}

    private function authorize(User $actor, Organisation $org, Brand $brand, bool $ownerOnly = false): void
    {
        $role = $this->members->organisationMember($org, (int) $actor->id)?->role_name;
        if ((int) $brand->organisation_id !== (int) $org->id
         || ! in_array($role, ['owner', 'manager'], true) || ($ownerOnly && $role !== 'owner')
         || ! $this->permissions->allows($actor, $brand, 'approval_request.update')
         || ! $this->permissions->allows($actor, $brand, 'approval_request.index')) {
            throw new AuthorizationException('Agent delegation management is forbidden.');
        }
    }

    public function listing(User $actor, Organisation $org, Brand $brand): array
    {
        $this->authorize($actor, $org, $brand);

        return ['enabled' => (bool) config('planner.agent_access_enabled', false),
            'delegated_publishing_available' => (bool) config('planner.agent_delegated_publishing_enabled', false),
            'grants' => $this->repository->list($org, $brand, $actor),
            'work_orders' => $this->repository->listOrders($org, $brand, 40),
            'autonomous_runs' => $this->autonomy->list($org, $brand, 40),
            'autonomous_research_available' => (bool) config('planner.agent_autonomy_enabled', false)];
    }

    public function detail(User $actor, Organisation $org, Brand $brand, string $uuid): array
    {
        $this->authorize($actor, $org, $brand);

        return $this->repository->orderDetails($org, $brand, $uuid);
    }

    public function issue(User $actor, Organisation $org, Brand $brand, array $data): array
    {
        $delegated = $data['mode'] === Policy::DELEGATED_PUBLISH;
        $this->authorize($actor, $org, $brand, $delegated);
        if (! config('planner.agent_access_enabled', false)) {
            throw new ServiceUnavailableHttpException(null, 'Agent access is not enabled.');
        }
        if ($delegated && ! config('planner.agent_delegated_publishing_enabled', false)) {
            throw new ServiceUnavailableHttpException(null, 'Delegated publishing is not enabled.');
        }
        foreach ($data['account_uuids'] as $uuid) {
            $this->studio->findAccount($org, $brand, $uuid);
        }
        if ($delegated && (! count($data['account_uuids']) || (int) $data['daily_arm_limit'] < 1)) {
            throw new \InvalidArgumentException('Delegated publishing requires explicit accounts and a nonzero daily arm limit.');
        }
        if (! $delegated && (int) $data['daily_arm_limit'] !== 0) {
            throw new \InvalidArgumentException('Review-required agents cannot arm publication jobs.');
        }

        return DB::transaction(function () use ($actor, $org, $brand, $data): array {
            Organisation::query()->whereKey($org->id)->lockForUpdate()->firstOrFail();
            Brand::query()->where('organisation_id', $org->id)->whereKey($brand->id)->lockForUpdate()->firstOrFail();
            $this->authorize($actor, $org, $brand, $data['mode'] === Policy::DELEGATED_PUBLISH);
            $result = $this->repository->issue($org, $brand, $actor, $data);
            $this->audit->record($org, $actor, 'agent.delegation.issued', 'planner_agent_delegation', (string) $result['grant']->id, $brand,
                ['mode' => $data['mode'], 'daily_arm_limit' => (int) $data['daily_arm_limit']]);

            return ['id' => (int) $result['grant']->id, 'plain_text_token' => $result['plain_text_token'],
                'expires_at' => $result['grant']->expires_at->toIso8601String(), 'mode' => $data['mode'],
                'warning' => 'Only shown once; connect through a trusted agent client. Do not place this token in prompts.'];
        }, 3);
    }

    public function startResearch(User $actor, Organisation $org, Brand $brand, int $grantId,
        array $payload, string $key, OrchestratePlannerAgent $orchestrator): array
    {
        $this->authorize($actor, $org, $brand);
        $grant = $this->repository->ownedGrant($org, $brand, $actor, $grantId);

        return $orchestrator->execute($actor, [
            'organisation' => $org, 'brand' => $brand, 'delegation' => $grant],
            'research.autorun', $key, $payload);
    }

    public function promoteResource(User $actor, Organisation $org, Brand $brand, int $grantId,
        string $uuid, string $key, OrchestratePlannerAgent $orchestrator): array
    {
        $this->authorize($actor, $org, $brand);
        $grant = $this->repository->ownedGrant($org, $brand, $actor, $grantId);

        return $orchestrator->execute($actor, ['organisation' => $org, 'brand' => $brand, 'delegation' => $grant],
            'resource.promote', $key, ['run_uuid' => $uuid]);
    }

    public function cancelResearch(User $actor, Organisation $org, Brand $brand, int $grantId,
        string $uuid, string $key, OrchestratePlannerAgent $orchestrator): array
    {
        $this->authorize($actor, $org, $brand);
        $grant = $this->repository->ownedGrant($org, $brand, $actor, $grantId);

        return $orchestrator->execute($actor, ['organisation' => $org, 'brand' => $brand, 'delegation' => $grant],
            'research.cancel', $key, ['run_uuid' => $uuid]);
    }

    /** Human-only review path: no MCP action or delegated token can change resource text. */
    public function reviseResource(User $actor, Organisation $org, Brand $brand, int $grantId,
        string $uuid, string $expectedHash, string $text, string $reason): array
    {
        $this->authorize($actor, $org, $brand);
        if (! config('planner.agent_access_enabled', false) || ! config('planner.agent_write_enabled', false)) {
            throw new AuthorizationException('Agent resource editing is disabled by deployment policy.');
        }

        return DB::transaction(function () use ($actor, $org, $brand, $grantId, $uuid, $expectedHash, $text, $reason): array {
            Organisation::query()->whereKey($org->id)->lockForUpdate()->firstOrFail();
            Brand::query()->whereKey($brand->id)->where('organisation_id', $org->id)->lockForUpdate()->firstOrFail();
            $this->authorize($actor, $org, $brand);
            if (! $this->permissions->allows($actor, $brand, 'content_item.store')) {
                throw new AuthorizationException('Content editing permission is no longer active.');
            }
            $grant = $this->repository->ownedGrant($org, $brand, $actor, $grantId);

            return $this->autonomy->revise($org, $brand, $grant, $actor, $uuid, $expectedHash, $text, $reason);
        }, 3);
    }

    public function revoke(User $actor,Organisation $org,Brand $brand,int $id): array
    {
        $this->authorize($actor,$org,$brand);

        return DB::transaction(function () use ($actor,$org,$brand,$id): array {
            $revoked = $this->repository->revoke($org,$brand,$actor,$id);
            if ($revoked) {
                $this->audit->record($org,$actor,'agent.delegation.revoked','planner_agent_delegation',(string) $id,$brand);
            }

            return ['revoked' => $revoked];
        },3);
    }
}
