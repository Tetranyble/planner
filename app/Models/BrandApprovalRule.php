<?php

namespace App\Models;

use App\Concerns\Uuidable;
use Database\Factories\BrandApprovalRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrandApprovalRule extends Model
{
    /** Explicitly deny mass assignment by default. Trusted repositories use forceFill. */
    protected $fillable = [];

    /** @use HasFactory<BrandApprovalRuleFactory> */
    use HasFactory, Uuidable;

    protected static function booted(): void
    {
        $requireDraft = function (self $rule): void {
            $policy = BrandApprovalPolicy::query()->findOrFail($rule->policy_id);
            if ($policy->state !== 'DRAFT') {
                throw new \LogicException('Rules belong to an immutable active/retired policy version.');
            }
        };
        static::saving($requireDraft);
        static::deleting($requireDraft);
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

    public function policy(): BelongsTo
    {
        return $this->belongsTo(BrandApprovalPolicy::class, 'policy_id');
    }
}
