<?php

namespace App\Models;

use Database\Factories\ApprovalRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ApprovalRequest extends Model
{
    /** @use HasFactory<ApprovalRequestFactory> */
    use HasFactory;

    protected $fillable = [

        'approval_policy_id',

        'expires_at',

        'matched_rule_id',

        'pending_target_marker',

        'request_kind',

        'requested_by_user_id',

        'required_approvals',

        'resolved_at',

        'status',

    ];

    protected function casts(): array
    {
        return ['policy_snapshot' => 'array', 'expires_at' => 'immutable_datetime', 'resolved_at' => 'immutable_datetime'];
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(ApprovalDecision::class, 'approval_request_id');
    }

    protected static function booted(): void
    {
        self::updating(function (self $request): void {
            if (array_diff(array_keys($request->getDirty()), ['status', 'resolved_at', 'updated_at']) !== []) {
                throw new \LogicException('Approval request evidence and policy snapshots are immutable.');
            }
            if ($request->getOriginal('status') !== 'PENDING') {
                throw new \LogicException('Resolved approval requests are immutable.');
            }
            if (! in_array($request->status, ['APPROVED', 'REJECTED', 'WITHDRAWN', 'EXPIRED'], true)) {
                throw new \LogicException('Invalid approval transition.');
            }
        });
        self::deleting(fn () => throw new \LogicException('Approval requests are historical records.'));
    }

    // Eloquent foreign-key relationships; keyed to the actual database columns.

    public function approvalPolicy(): BelongsTo
    {
        return $this->belongsTo(BrandApprovalPolicy::class, 'approval_policy_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function contentTarget(): BelongsTo
    {
        return $this->belongsTo(ContentTarget::class, 'content_target_id');
    }

    public function matchedRule(): BelongsTo
    {
        return $this->belongsTo(BrandApprovalRule::class, 'matched_rule_id');
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }
}
