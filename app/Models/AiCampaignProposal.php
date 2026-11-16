<?php

namespace App\Models;

use App\Concerns\Uuidable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AiCampaignProposal extends Model
{
    use HasFactory, Uuidable;

    protected $fillable = [

        'audience',

        'duration_days',

        'model',

        'objective',

        'platforms',

        'prompt_hash',

        'proposal_text',

        'provider_evidence_hash',

        'provider_evidence_snapshot',

        'result_hash',

        'title',

    ];

    protected function casts(): array
    {
        return ['platforms' => 'array', 'provider_evidence_snapshot' => 'array', 'reviewed_at' => 'immutable_datetime'];
    }

    // Eloquent foreign-key relationships; keyed to the actual database columns.

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function reviewedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
