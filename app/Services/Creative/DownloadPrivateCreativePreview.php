<?php

namespace App\Services\Creative;

use App\Authorization\BrandPermissionGuard;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\BrandMemberRepositoryContract;
use App\Repository\Contracts\CreativeImageHandoffRepositoryContract;
use App\Services\Ai\BrandAiUsageBudget;
use Illuminate\Auth\Access\AuthorizationException;

/** User-triggered private preview, never an agent publishing action. */
final class DownloadPrivateCreativePreview
{
    public function __construct(
        private readonly BrandMemberRepositoryContract $members,
        private readonly BrandPermissionGuard $permissions,
        private readonly BrandAiUsageBudget $budget,
        private readonly GeneratePrivateCreativePreview $provider,
        private readonly CreativeImageHandoffRepositoryContract $handoffs,
    ) {}

    private function authorize(User $actor, Organisation $org, Brand $brand): void
    {
        $role = $this->members->organisationMember($org, (int) $actor->id)?->role_name;
        if ((int) $brand->organisation_id !== (int) $org->id
            || ! in_array($role, ['owner', 'manager', 'contributor'], true)
            || ! $this->permissions->allows($actor, $brand, 'media_asset.store')) {
            throw new AuthorizationException('Brand creative access is not available.');
        }
    }

    public function create(User $actor, Organisation $org, Brand $brand, string $prompt, string $size): array
    {
        $this->authorize($actor, $org, $brand);
        if (! config('planner.creative_image_enabled', false)) {
            throw new AuthorizationException('Private creative previews are disabled by deployment policy.');
        }
        if (! in_array($size, ['1024x1024', '1536x1024', '1024x1536'], true)
            || mb_strlen($prompt) < 20 || mb_strlen($prompt) > 1400
            || (string) config('services.planner_ai.key') === '') {
            throw new \InvalidArgumentException('Invalid private image request or provider configuration.');
        }
        $reservation = $this->budget->reserve($org, $brand, $actor, 'CREATIVE_IMAGE_PREVIEW');
        try {
            $image = $this->provider->generate($prompt, $size);
            $this->authorize($actor, $org, $brand);
            // Only an expiring, workspace-bound checksum ticket is durable.
            $handoff = $this->handoffs->issue($actor, $org, $brand, $image,
                max(5, min(120, (int) config('planner.creative_handoff_ttl_minutes', 60))));
            $this->budget->settle($reservation, true, $image['model'], $image['sha256'], $image['provider_usage']);

            return $image + ['handoff_uuid' => $handoff->uuid, 'expires_at' => $handoff->expires_at->toIso8601String()];
        } catch (\Throwable $error) {
            // No automatic retry: the remote request may have been billed.
            $this->budget->markUncertain($reservation);
            throw $error;
        }
    }
}
