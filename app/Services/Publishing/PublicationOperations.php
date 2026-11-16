<?php

namespace App\Services\Publishing;

use App\Authorization\BrandPermissionGuard;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\PublicationOperationsRepositoryContract;
use Illuminate\Auth\Access\AuthorizationException;

final class PublicationOperations
{
    public function __construct(private readonly BrandPermissionGuard $guard,
        private readonly PublicationOperationsRepositoryContract $repo) {}

    private function check(User $actor, Organisation $org, Brand $brand): void
    {
        if ((int) $brand->organisation_id !== (int) $org->id ||
            ! $this->guard->allows($actor, $brand, 'approval_request.index')) {
            throw new AuthorizationException('Publication operations access denied.');
        }
    }

    public function overview(User $actor, Organisation $org, Brand $brand): array
    {
        $this->check($actor, $org, $brand);
        $dashboard = $this->repo->dashboard($org, $brand, 100);
        $dashboard['execution'] = [
            'enabled' => ExecutePublicationPlans::isEnabled(),
            'supported_live_text_providers' => ['facebook', 'linkedin', 'x'],
            'automatic_retry' => false,
            'media_publishing_enabled' => false,
            'facebook_page_token_enabled' => (bool) config('social_integrations.publishing_entitlements.facebook', false),
            'worker_command' => 'planner:process-publication-execution',
        ];

        return $dashboard;
    }

    public function history(User $actor, Organisation $org, Brand $brand, int $jobId): array
    {
        $this->check($actor, $org, $brand);

        return $this->repo->timeline($org,$brand,$jobId,100);
    }
}
