<?php

namespace App\Models;

use Database\Factories\ApprovalDecisionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ApprovalDecision extends Model
{
    /** @use HasFactory<ApprovalDecisionFactory> */
    use HasFactory;

    protected $fillable = ['comment'];

    protected static function booted(): void
    {
        self::updating(fn () => throw new \LogicException('Approval decisions are append-only.'));
        self::deleting(fn () => throw new \LogicException('Approval decisions cannot be deleted.'));
    }

    // Eloquent foreign-key relationships; keyed to the actual database columns.

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class, 'approval_request_id');
    }

    public function approverUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }
}
