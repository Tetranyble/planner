<?php

namespace Database\Factories;

use App\Models\AiCampaignProposal;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<AiCampaignProposal> */
final class AiCampaignProposalFactory extends Factory
{
    protected $model = AiCampaignProposal::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(), 'organisation_id' => Organisation::factory(),
            'brand_id' => fn (array $a) => Brand::factory()->create(['organisation_id' => $a['organisation_id']])->id,
            'requested_by_user_id' => User::factory(), 'title' => 'Sample launch campaign',
            'objective' => 'Introduce the service', 'audience' => 'Small businesses',
            'platforms' => ['linkedin', 'facebook'], 'duration_days' => 7,
            'proposal_text' => 'DRAFT campaign plan. Each claim must be verified before use.',
            'model' => 'fixture', 'prompt_hash' => hash('sha256', 'fixture prompt'),
            'result_hash' => hash('sha256', 'DRAFT campaign plan. Each claim must be verified before use.'),
            'status' => 'PROPOSED',
        ];
    }
}
