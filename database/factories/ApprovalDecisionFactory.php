<?php

namespace Database\Factories;

use App\Models\ApprovalDecision;
use App\Models\ApprovalRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ApprovalDecision> */
final class ApprovalDecisionFactory extends Factory
{
    protected $model = ApprovalDecision::class;

    public function definition(): array
    {
        return ['decision' => 'APPROVE', 'decided_at' => now(), 'comment' => 'Reviewed against the immutable target payload.'];
    }

    public function forRequest(ApprovalRequest $request, User $reviewer): static
    {
        return $this->state(fn () => ['organisation_id' => $request->organisation_id, 'brand_id' => $request->brand_id,
            'approval_request_id' => $request->id, 'approver_user_id' => $reviewer->id]);
    }
}
