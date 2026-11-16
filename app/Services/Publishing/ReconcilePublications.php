<?php

namespace App\Services\Publishing;

use App\Authorization\BrandPermissionGuard;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\PublicationReconciliationRepositoryContract;
use Illuminate\Auth\Access\AuthorizationException;

final class ReconcilePublications
{
    public function __construct(private readonly BrandPermissionGuard $guard, private readonly PublicationReconciliationRepositoryContract $repo) {}

    private function check(User $actor, Organisation $org, Brand $brand, string $permission): void
    {
        if ((int) $brand->organisation_id !== (int) $org->id || ! $this->guard->allows($actor, $brand, $permission)) {
            throw new AuthorizationException('Publication reconciliation access denied.');
        }
    }

    public function history(User $actor, Organisation $org, Brand $brand, int $id): array
    {
        $this->check($actor, $org, $brand, 'approval_request.index');

        return $this->repo->history($org, $brand, $id);
    }

    public function submit(User $actor, Organisation $org, Brand $brand, int $id, array $payload): array
    {
        $this->check($actor, $org, $brand, 'approval_request.update');

        return $this->repo->submit($org, $brand, $id, (int) $actor->id, $payload);
    }

    public function decide(User $actor, Organisation $org, Brand $brand, int $id, array $payload): array
    {
        $this->check($actor, $org, $brand, 'content.approve');

        return $this->repo->decide($org,$brand,$id,(int) $actor->id,$payload);
    }
}
