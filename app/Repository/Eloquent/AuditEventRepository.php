<?php

namespace App\Repository\Eloquent;

use App\Enums\Operation\AuditActorKind;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\AuditEventRepositoryContract;
use Illuminate\Support\Facades\DB;

final class AuditEventRepository implements AuditEventRepositoryContract
{
    public function record(
        Organisation $organisation,
        User $actor,
        string $action,
        string $resourceKind,
        string $resourceId,
        ?Brand $brand = null,
        array $metadata = [],
    ): void {
        $now = now();
        DB::table('audit_logs')->insert([
            'organisation_id' => $organisation->getKey(),
            'actor_kind' => AuditActorKind::USER->value,
            'actor_id' => (string) $actor->getKey(),
            'action' => $action,
            'resource_kind' => $resourceKind,
            'resource_id' => $resourceId,
            'brand_ref' => $brand?->getKey(),
            'occurred_at' => $now,
            'details' => json_encode(['source' => 'planner.application.v1', ...$metadata], JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
