<?php

namespace App\Repository\Contracts;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\PlannerAgentActionReceipt;
use App\Models\PlannerAgentDelegation;
use App\Models\PlannerAgentWorkOrder;
use App\Models\User;

interface PlannerAgentRepositoryContract
{
    public function issue(Organisation $org, Brand $brand, User $owner, array $data): array;

    public function list(Organisation $org, Brand $brand, User $owner): array;

    public function revoke(Organisation $org, Brand $brand, User $owner, int $id): bool;

    /** @return array{organisation:Organisation,brand:Brand,delegation:PlannerAgentDelegation}|null */
    public function scopeForToken(int $tokenId, User $actor): ?array;

    public function lockedForAction(Organisation $org, Brand $brand, User $actor, int $delegationId): PlannerAgentDelegation;

    public function receipt(PlannerAgentDelegation $grant, string $key): ?PlannerAgentActionReceipt;

    public function dailyCount(PlannerAgentDelegation $grant, ?string $action = null): int;

    public function storeReceipt(PlannerAgentDelegation $grant, string $action, string $key, string $hash, array $result): void;

    public function createOrder(Organisation $org, Brand $brand, User $actor, PlannerAgentDelegation $grant, array $data): PlannerAgentWorkOrder;

    public function order(Organisation $org, Brand $brand, string $uuid, bool $lock = false): PlannerAgentWorkOrder;

    public function updateOrder(PlannerAgentWorkOrder $order, array $fields, string $action, array $evidence): void;

    public function listOrders(Organisation $org, Brand $brand, int $limit, ?int $delegationId = null): array;

    public function ownedGrant(Organisation $org, Brand $brand, User $owner, int $grantId): PlannerAgentDelegation;

    public function orderDetails(Organisation $org,Brand $brand,string $uuid): array;
}
