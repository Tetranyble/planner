<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\PlannerAgentDelegation;
use App\Models\PlannerAgentWorkOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

final class PlannerAgentWorkOrderFactory extends Factory
{
    protected $model = PlannerAgentWorkOrder::class;

    public function definition(): array
    {
        return ['uuid' => (string) Str::uuid(), 'title' => 'Research brief', 'objective' => 'Investigate a topic for content planning', 'findings' => 'Unverified research notes', 'sources' => [], 'research_hash' => hash('sha256', 'test'), 'status' => 'RESEARCH_RECORDED'];
    }

    public function forScope(Organisation $org, Brand $brand, User $actor, PlannerAgentDelegation $delegation): static
    {
        return $this->state(fn (): array => ['organisation_id' => $org->id, 'brand_id' => $brand->id, 'created_by_user_id' => $actor->id, 'delegation_id' => $delegation->id]);
    }
}
