<?php

namespace App\Repository\Contracts;

use App\Models\AiCampaignProposal;
use App\Models\AiCampaignProposalDraft;
use App\Models\Brand;
use App\Models\ContentItem;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AiCampaignProposalRepositoryContract
{
    public function list(Organisation $org, Brand $brand): LengthAwarePaginator;

    public function find(Organisation $org, Brand $brand, string $uuid, bool $lock = false): AiCampaignProposal;

    public function create(Organisation $org, Brand $brand, User $actor, array $values): AiCampaignProposal;

    public function existingDraft(Organisation $org, Brand $brand, AiCampaignProposal $proposal): ?AiCampaignProposalDraft;

    public function linkDraft(Organisation $org, Brand $brand, AiCampaignProposal $proposal, ContentItem $item, User $actor): void;

    public function review(AiCampaignProposal $proposal, User $reviewer, string $decision, string $note): void;
}
