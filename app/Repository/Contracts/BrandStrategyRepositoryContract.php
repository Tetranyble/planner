<?php

namespace App\Repository\Contracts;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;

/** All reads and writes MUST include both organisation and brand. */
interface BrandStrategyRepositoryContract
{
    public function snapshot(Organisation $organisation, Brand $brand): array;

    public function saveProfile(Organisation $organisation, Brand $brand, User $actor, array $data): void;

    public function createAudience(Organisation $organisation, Brand $brand, array $data): string;

    public function createVoice(Organisation $organisation, Brand $brand, array $data): string;

    public function saveClaimPolicy(Organisation $organisation, Brand $brand, array $data): void;

    public function createDraftClaim(Organisation $organisation, Brand $brand, User $actor, array $data): string;

    public function createDraftApprovalPolicy(Organisation $organisation, Brand $brand, User $actor, array $data): string;
}
