<?php

namespace App\Repository\Eloquent;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\PlannerAgentActionReceipt;
use App\Models\PlannerAgentDelegation;
use App\Models\PlannerAgentWorkEvent;
use App\Models\PlannerAgentWorkOrder;
use App\Models\User;
use App\Repository\Contracts\PlannerAgentRepositoryContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class PlannerAgentRepository implements PlannerAgentRepositoryContract
{
    public function issue(Organisation $org, Brand $brand, User $owner, array $data): array
    {
        return DB::transaction(function () use ($org, $brand, $owner, $data): array {
            $expires = now()->addDays((int) $data['expires_in_days']);
            $token = $owner->createToken('planner-agent:'.$brand->id.':'.$data['name'], ['planner:agent'], $expires);
            $grant = PlannerAgentDelegation::query()->create([
                'organisation_id' => $org->id, 'brand_id' => $brand->id, 'user_id' => $owner->id,
                'sanctum_token_id' => $token->accessToken->getKey(), 'name' => $data['name'],
                'mode' => $data['mode'], 'account_uuids' => $data['account_uuids'],
                'daily_action_limit' => $data['daily_action_limit'], 'daily_arm_limit' => $data['daily_arm_limit'],
                'expires_at' => $expires,
            ]);

            return ['grant' => $grant, 'plain_text_token' => $token->plainTextToken];
        }, 3);
    }

    public function list(Organisation $org, Brand $brand, User $owner): array
    {
        return PlannerAgentDelegation::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->where('user_id', $owner->id)->latest('id')->limit(60)->get()
            ->map(fn ($g) => ['id' => (int) $g->id, 'name' => $g->name, 'mode' => $g->mode,
                'account_uuids' => $g->account_uuids, 'daily_action_limit' => (int) $g->daily_action_limit,
                'daily_arm_limit' => (int) $g->daily_arm_limit, 'expires_at' => $g->expires_at?->toIso8601String(),
                'revoked_at' => $g->revoked_at?->toIso8601String()])->all();
    }

    public function revoke(Organisation $org, Brand $brand, User $owner, int $id): bool
    {
        return DB::transaction(function () use ($org, $brand, $owner, $id): bool {
            $row = PlannerAgentDelegation::query()->whereKey($id)->where('organisation_id', $org->id)
                ->where('brand_id', $brand->id)->where('user_id', $owner->id)->lockForUpdate()->first();
            if (! $row) {
                return false;
            }
            if ($row->revoked_at) {
                return true;
            }
            PersonalAccessToken::find($row->sanctum_token_id)?->delete();
            $row->forceFill(['revoked_at' => now()])->save();

            return true;
        }, 3);
    }

    public function scopeForToken(int $tokenId, User $actor): ?array
    {
        $grant = PlannerAgentDelegation::query()->where('sanctum_token_id', $tokenId)
            ->where('user_id', $actor->id)->whereNull('revoked_at')->where('expires_at', '>', now())->first();
        if (! $grant) {
            return null;
        }
        $organisation = Organisation::query()->find($grant->organisation_id);
        $brand = Brand::query()->whereKey($grant->brand_id)->where('organisation_id', $grant->organisation_id)->first();

        return $organisation && $brand ? ['organisation' => $organisation, 'brand' => $brand, 'delegation' => $grant] : null;
    }

    public function lockedForAction(Organisation $org, Brand $brand, User $actor, int $delegationId): PlannerAgentDelegation
    {
        $grant = PlannerAgentDelegation::query()->whereKey($delegationId)->where('organisation_id', $org->id)
            ->where('brand_id', $brand->id)->where('user_id', $actor->id)->whereNull('revoked_at')
            ->where('expires_at', '>', now())->lockForUpdate()->first();
        if (! $grant) {
            throw new AccessDeniedHttpException('Agent delegation is revoked, expired or has changed.');
        }

        return $grant;
    }

    public function receipt(PlannerAgentDelegation $grant, string $key): ?PlannerAgentActionReceipt
    {
        return PlannerAgentActionReceipt::query()->where('delegation_id', $grant->id)->where('idempotency_key', $key)->first();
    }

    public function dailyCount(PlannerAgentDelegation $grant, ?string $action = null): int
    {
        return PlannerAgentActionReceipt::query()->where('delegation_id', $grant->id)
            ->where('created_at', '>=', now('UTC')->startOfDay())->where('created_at', '<', now('UTC')->addDay()->startOfDay())
            ->when($action, fn ($q) => $q->where('action', $action))->count();
    }

    public function storeReceipt(PlannerAgentDelegation $grant, string $action, string $key, string $hash, array $result): void
    {
        PlannerAgentActionReceipt::query()->create(['organisation_id' => $grant->organisation_id, 'brand_id' => $grant->brand_id,
            'delegation_id' => $grant->id, 'action' => $action, 'idempotency_key' => $key, 'input_hash' => $hash,
            'result' => $result, 'created_at' => now('UTC')]);
    }

    public function createOrder(Organisation $org, Brand $brand, User $actor, PlannerAgentDelegation $grant, array $data): PlannerAgentWorkOrder
    {
        $order = PlannerAgentWorkOrder::query()->create([
            'uuid' => (string) Str::uuid(), 'organisation_id' => $org->id, 'brand_id' => $brand->id, 'delegation_id' => $grant->id,
            'created_by_user_id' => $actor->id, 'title' => $data['title'], 'objective' => $data['objective'],
            'sources' => $data['sources'], 'findings' => $data['findings'], 'research_hash' => $data['research_hash'],
            'status' => 'RESEARCH_RECORDED',
        ]);
        PlannerAgentWorkEvent::query()->create(['work_order_id' => $order->id, 'action' => 'research.capture',
            'evidence' => ['research_hash' => $order->research_hash, 'unverified_source_count' => count($data['sources'])], 'created_at' => now('UTC')]);

        return $order;
    }

    public function order(Organisation $org, Brand $brand, string $uuid, bool $lock = false): PlannerAgentWorkOrder
    {
        return PlannerAgentWorkOrder::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->where('uuid', $uuid)->when($lock, fn ($q) => $q->lockForUpdate())->firstOrFail();
    }

    public function updateOrder(PlannerAgentWorkOrder $order, array $fields, string $action, array $evidence): void
    {
        $order->forceFill($fields)->save();
        PlannerAgentWorkEvent::query()->create(['work_order_id' => $order->id, 'action' => $action,
            'evidence' => $evidence, 'created_at' => now('UTC')]);
    }

    public function orderDetails(Organisation $org, Brand $brand, string $uuid): array
    {
        $order = $this->order($org, $brand, $uuid);
        $events = PlannerAgentWorkEvent::query()->where('work_order_id', $order->id)->orderBy('id')->limit(100)->get()
            ->map(fn ($e) => ['action' => $e->action, 'evidence' => $e->evidence, 'created_at' => $e->created_at?->toIso8601String()])->all();

        return ['uuid' => $order->uuid, 'title' => $order->title, 'objective' => $order->objective,
            'findings' => $order->findings, 'sources' => $order->sources, 'research_hash' => $order->research_hash,
            'status' => $order->status, 'draft_content_uuid' => $order->draft_content_uuid,
            'research_verified' => false, 'events' => $events];
    }

    public function ownedGrant(Organisation $org, Brand $brand, User $owner, int $grantId): PlannerAgentDelegation
    {
        return PlannerAgentDelegation::query()->whereKey($grantId)->where('organisation_id', $org->id)
            ->where('brand_id', $brand->id)->where('user_id', $owner->id)
            ->whereNull('revoked_at')->where('expires_at', '>', now('UTC'))->firstOrFail();
    }

    public function listOrders(Organisation $org, Brand $brand, int $limit, ?int $delegationId = null): array
    {
        return PlannerAgentWorkOrder::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->when($delegationId, fn ($q) => $q->where('delegation_id', $delegationId))
            ->latest('id')->limit(max(1, min(100,$limit)))->get()
            ->map(fn ($w) => ['uuid' => $w->uuid, 'title' => $w->title, 'status' => $w->status,
                'draft_content_uuid' => $w->draft_content_uuid, 'research_hash' => $w->research_hash,
                'sources_count' => count($w->sources ?? []), 'created_at' => $w->created_at?->toIso8601String()])->all();
    }
}
