<?php

namespace App\Models;

use App\Concerns\Uuidable;
use Database\Factories\BrandApprovalPolicyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BrandApprovalPolicy extends Model
{
    /** Explicitly deny mass assignment by default. Trusted repositories use forceFill. */
    protected $fillable = [];

    /** @use HasFactory<BrandApprovalPolicyFactory> */
    use HasFactory, Uuidable;

    protected static function booted(): void
    {
        static::updating(function (self $policy): void {
            $previous = $policy->getOriginal('state');
            if ($previous === 'RETIRED') {
                throw new \LogicException('Retired approval policies are immutable.');
            }
            if ($previous === 'ACTIVE') {
                if ($policy->state !== 'RETIRED'
                    || $policy->isDirty(['description', 'policy_version',
                        'external_publishing_requires_approval', 'forbid_self_approval',
                        'default_required_approvals', 'activated_at', 'activated_by_user_id'])) {
                    throw new \LogicException('Active policies may only be retired.');
                }
            }
        });
    }

    public function rules(): HasMany
    {
        return $this->hasMany(BrandApprovalRule::class, 'policy_id');
    }

    // Eloquent foreign-key relationships; keyed to the actual database columns.

    public function activatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by_user_id');
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
