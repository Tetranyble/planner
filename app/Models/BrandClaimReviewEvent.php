<?php

namespace App\Models;

use App\Concerns\Uuidable;
use Database\Factories\BrandClaimReviewEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only history. Application code never updates or deletes a recorded decision. */
class BrandClaimReviewEvent extends Model
{
    /** @use HasFactory<BrandClaimReviewEventFactory> */
    use HasFactory, Uuidable;

    public $timestamps = false;

    protected $fillable = [];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Claim review events are append-only.'));
        static::deleting(fn () => throw new \LogicException('Claim review events cannot be deleted.'));
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

    public function reviewerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_user_id');
    }
}
