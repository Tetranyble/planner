<?php

namespace App\Repository\Contracts;

use App\Models\Organisation;
use App\Models\ProviderGrantReconciliationDecision;
use App\Models\ProviderGrantReconciliationEvidence;

interface ProviderGrantReconciliationRepositoryContract
{
    /** @return list<array<string,mixed>> Tenant-scoped unresolved cases and evidence snapshots. */
    public function directory(Organisation $organisation, int $actorId): array;

    /** @param array{source_type:string,reference:string,summary:string} $data */
    public function submit(Organisation $organisation, string $requestUuid, int $actorId, array $data): ProviderGrantReconciliationEvidence;

    /** @param array{evidence_uuid:string,outcome:string,rationale:string} $data */
    public function decide(Organisation $organisation, string $requestUuid, int $actorId, array $data): ProviderGrantReconciliationDecision;
}
