<?php

namespace Database\Factories;

use App\Models\AiCampaignProposal;
use App\Models\AiCampaignProposalDraft;
use App\Models\Brand;
use App\Models\ContentItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AiCampaignProposalDraft> */
final class AiCampaignProposalDraftFactory extends Factory
{
    protected $model = AiCampaignProposalDraft::class;

    /** An explicitly scoped fixture; never generate unrelated tenants by default. */
    public function forApprovedProposal(Brand $brand, AiCampaignProposal $proposal, ContentItem $item, User $editor): static
    {
        return $this->state(fn () => ['organisation_id' => $brand->organisation_id,
            'brand_id' => $brand->id, 'ai_campaign_proposal_id' => $proposal->id,
            'content_item_id' => $item->id, 'created_by_user_id' => $editor->id,
            'approved_proposal_hash' => $proposal->result_hash]);
    }

    public function definition(): array
    {
        return [
            'organisation_id' => null, 'brand_id' => null, 'ai_campaign_proposal_id' => null,
            'content_item_id' => null, 'created_by_user_id' => null,
            'approved_proposal_hash' => hash('sha256', 'factory-approved-campaign-proposal'),
        ];
    }
}
