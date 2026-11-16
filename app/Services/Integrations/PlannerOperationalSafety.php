<?php

namespace App\Services\Integrations;

use App\Authorization\BrandPermissionGuard;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\PlannerAlertMailRepositoryContract;
use App\Repository\Contracts\PlannerOperationalAlertRepositoryContract;
use App\Repository\Contracts\PlannerOperationalSafetyRepositoryContract;
use Illuminate\Auth\Access\AuthorizationException;

final class PlannerOperationalSafety
{
    public function __construct(private readonly BrandPermissionGuard $guard,
        private readonly PlannerOperationalSafetyRepositoryContract $repository,
        private readonly PlannerOperationalAlertRepositoryContract $alerts,
        private readonly PlannerAlertMailRepositoryContract $mail) {}

    public function show(User $actor, Organisation $org, Brand $brand): array
    {
        if ((int) $brand->organisation_id !== (int) $org->id ||
            ! $this->guard->allows($actor, $brand, 'approval_request.index')) {
            throw new AuthorizationException('Operational safety access denied.');
        }
        $counts = $this->repository->summary($org, $brand);

        return ['source' => 'planner_database', 'as_of_utc' => now('UTC')->toIso8601String(),
            'counts' => $counts,
            'historical_alerts' => $this->alerts->list($org, $brand),
            'alert_mail_delivery' => $this->mail->summary((int) $org->id, (int) $brand->id),
            'attention_required' => array_sum($counts) > 0,
            'automatic_repost_allowed' => false,
            'note' => 'Counts are brand-scoped workflow states, not proof of provider delivery or automatic correction.'];
    }
}
