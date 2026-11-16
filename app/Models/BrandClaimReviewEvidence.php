<?php

namespace App\Models;

use App\Concerns\Uuidable;
use Database\Factories\BrandClaimReviewEvidenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The exact trusted evidence hashes used to approve a claim. Append-only. */
class BrandClaimReviewEvidence extends Model
{
    /** @use HasFactory<BrandClaimReviewEvidenceFactory> */
    use HasFactory, Uuidable;

    protected $table = 'brand_claim_review_evidence';

    public $timestamps = false;

    protected $fillable = [];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Evidence snapshots cannot be changed.'));
        static::deleting(fn () => throw new \LogicException('Evidence snapshots cannot be removed.'));
    }

    // Eloquent foreign-key relationships; keyed to the actual database columns.

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function claimEvidence(): BelongsTo
    {
        return $this->belongsTo(BrandClaimEvidence::class, 'claim_evidence_id');
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    public function reviewEvent(): BelongsTo
    {
        return $this->belongsTo(BrandClaimReviewEvent::class, 'review_event_id');
    }
}
