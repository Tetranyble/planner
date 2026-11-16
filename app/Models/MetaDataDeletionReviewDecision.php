<?php

namespace App\Models;

use Database\Factories\MetaDataDeletionReviewDecisionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only legal decision. No update/delete path is exposed. */
final class MetaDataDeletionReviewDecision extends Model
{
    /** @use HasFactory<MetaDataDeletionReviewDecisionFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [

        'disposition',

        'evidence_reference',

        'explanation',

        'legal_basis',

        'organisation_id',

        'review_again_at',

        'reviewed_by_user_id',

        'target_id',

    ];

    protected static function booted(): void
    {
        self::updating(fn () => throw new \LogicException('Privacy review decisions are append-only.'));
        self::deleting(fn () => throw new \LogicException('Privacy review decisions cannot be deleted.'));
    }

    protected $casts = ['review_again_at' => 'immutable_datetime'];

    // Eloquent foreign-key relationships; keyed to the actual database columns.

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    public function reviewedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
