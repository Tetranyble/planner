<?php

namespace App\Repository\Eloquent;

use App\Models\AiCampaignProposal;
use App\Models\AiCampaignProposalDraft;
use App\Models\Brand;
use App\Models\ContentItem;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\AiCampaignProposalRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

final class AiCampaignProposalRepository implements AiCampaignProposalRepositoryContract
{
    private function scoped(Organisation $org, Brand $brand)
    {
        return AiCampaignProposal::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id);
    }

    public function list(Organisation $org, Brand $brand): LengthAwarePaginator
    {
        return $this->scoped($org, $brand)->orderByDesc('id')->paginate(20);
    }

    public function find(Organisation $org, Brand $brand, string $uuid, bool $lock = false): AiCampaignProposal
    {
        $q = $this->scoped($org, $brand)->where('uuid', $uuid);

        return ($lock ? $q->lockForUpdate() : $q)->firstOrFail();
    }

    public function create(Organisation $org, Brand $brand, User $actor, array $values): AiCampaignProposal
    {
        return AiCampaignProposal::query()->forceCreate([...$values,
            'uuid' => (string) Str::uuid(), 'organisation_id' => $org->id, 'brand_id' => $brand->id,
            'requested_by_user_id' => $actor->id, 'status' => 'PROPOSED']);
    }

    public function existingDraft(Organisation $org, Brand $brand, AiCampaignProposal $proposal): ?AiCampaignProposalDraft
    {
        return AiCampaignProposalDraft::query()->where('organisation_id', $org->id)
            ->where('brand_id', $brand->id)->where('ai_campaign_proposal_id', $proposal->id)->first();
    }

    public function linkDraft(Organisation $org, Brand $brand, AiCampaignProposal $proposal, ContentItem $item, User $actor): void
    {
        (new AiCampaignProposalDraft)->forceFill([
            'organisation_id' => $org->id, 'brand_id' => $brand->id,
            'ai_campaign_proposal_id' => $proposal->id, 'content_item_id' => $item->id,
            'approved_proposal_hash' => $proposal->result_hash, 'created_by_user_id' => $actor->id,
        ])->save();
    }

    public function review(AiCampaignProposal $proposal, User $reviewer, string $decision, string $note): void
    {
        $proposal->status = $decision;
        $proposal->reviewed_by_user_id = $reviewer->id;
        $proposal->review_note = $note;
        $proposal->reviewed_at = now();
        $proposal->save();
    }
}
