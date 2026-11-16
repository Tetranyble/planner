<?php

namespace App\Models;

use App\Concerns\Uuidable;
use Database\Factories\BrandClaimFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrandClaim extends Model
{
    /** Explicitly deny mass assignment by default. Trusted repositories use forceFill. */
    protected $fillable = [];

    /** @use HasFactory<BrandClaimFactory> */
    use HasFactory, Uuidable;

    protected static function booted(): void
    {
        static::updating(function (self $claim): void {
            if ($claim->getOriginal('status') !== 'DRAFT'
                && $claim->isDirty(['claim_text', 'claim_category', 'valid_from', 'valid_until'])) {
                throw new \LogicException('Submitted and reviewed claims cannot be rewritten in place.');
            }
        });
    }

    // Eloquent foreign-key relationships; keyed to the actual database columns.

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
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
