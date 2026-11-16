<?php

namespace App\Models;

use App\Concerns\Uuidable;
use Database\Factories\BrandClaimEvidenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrandClaimEvidence extends Model
{
    protected $table = 'brand_claim_evidence';

    /** Explicitly deny mass assignment by default. Trusted repositories use forceFill. */
    protected $fillable = [];

    /** @use HasFactory<BrandClaimEvidenceFactory> */
    use HasFactory, Uuidable;

    public function knowledgeSource(): BelongsTo
    {
        return $this->belongsTo(BrandKnowledgeSource::class, 'knowledge_source_id');
    }

    // Eloquent foreign-key relationships; keyed to the actual database columns.

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(BrandClaim::class, 'claim_id');
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    public function reviewedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function submittedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }
}
