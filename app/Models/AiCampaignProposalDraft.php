<?php

namespace App\Models;

use Database\Factories\AiCampaignProposalDraftFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AiCampaignProposalDraft extends Model
{
    /** @use HasFactory<AiCampaignProposalDraftFactory> */
    use HasFactory;

    protected $fillable = [];

    // Eloquent foreign-key relationships; keyed to the actual database columns.

    public function aiCampaignProposal(): BelongsTo
    {
        return $this->belongsTo(AiCampaignProposal::class, 'ai_campaign_proposal_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function contentItem(): BelongsTo
    {
        return $this->belongsTo(ContentItem::class, 'content_item_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }
}
