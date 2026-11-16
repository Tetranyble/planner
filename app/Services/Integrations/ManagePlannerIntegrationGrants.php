<?php

namespace App\Services\Integrations;

use App\Authorization\BrandPermissionGuard;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\BrandMemberRepositoryContract;
use App\Repository\Contracts\PlannerIntegrationGrantRepositoryContract;
use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

final class ManagePlannerIntegrationGrants
{
    public function __construct(private readonly BrandPermissionGuard $guard,
        private readonly PlannerIntegrationGrantRepositoryContract $grants,
        private readonly BrandMemberRepositoryContract $members) {}

    private function authorize(User $actor, Organisation $org, Brand $brand): void
    {
        $membership = $this->members->organisationMember($org, (int) $actor->getKey());
        if ($membership === null || ! in_array($membership->role_name, ['owner', 'manager'], true) ||
            (int) $brand->organisation_id !== (int) $org->id ||
            ! $this->guard->allows($actor, $brand, 'approval_request.update') ||
            ! $this->guard->allows($actor, $brand, 'approval_request.index')) {
            throw new AuthorizationException('Integration management denied.');
        }
    }

    public function list(User $actor, Organisation $org, Brand $brand): array
    {
        $this->authorize($actor, $org, $brand);

        return ['enabled' => (bool) config('planner.integration_read_enabled', false),
            'grants' => $this->grants->list($org, $brand, $actor)];
    }

    public function issue(User $actor, Organisation $org, Brand $brand, string $name, int $days): array
    {
        $this->authorize($actor, $org, $brand);
        if (! config('planner.integration_read_enabled', false)) {
            throw new ServiceUnavailableHttpException(null, 'External integrations are not enabled.');
        }
        $result = $this->grants->issue($org, $brand, $actor, $name, $days);

        return ['id' => $result['grant']->id, 'name' => $name,
            'expires_at' => $result['grant']->expires_at->toIso8601String(),
            'plain_text_token' => $result['plain_text_token'],
            'warning' => 'Copy this token now. Planner never returns it again.'];
    }

    public function revoke(User $actor, Organisation $org, Brand $brand, int $grantId): array
    {
        $this->authorize($actor, $org, $brand);
        if (! $this->grants->revoke($org, $brand, $actor, $grantId)) {
            throw new NotFoundHttpException('Grant not found.');
        }

        return ['revoked' => true];
    }
}
