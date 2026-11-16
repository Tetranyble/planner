<?php

namespace App\Models;

use Database\Factories\PrivacyRetentionFollowupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A follow-up is a reminder, NOT a new legal decision or proof of erasure. */
final class PrivacyRetentionFollowup extends Model
{
    /** @use HasFactory<PrivacyRetentionFollowupFactory> */
    use HasFactory;

    protected $fillable = [

        'acknowledged_at',

        'acknowledged_by_user_id',

        'acknowledgement_notes',

        'due_at',

        'organisation_id',

        'review_decision_id',

        'status',

    ];

    protected $casts = [
        'due_at' => 'immutable_datetime', 'acknowledged_at' => 'immutable_datetime',
    ];

    // Eloquent foreign-key relationships; keyed to the actual database columns.

    public function acknowledgedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by_user_id');
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    public function reviewDecision(): BelongsTo
    {
        return $this->belongsTo(MetaDataDeletionReviewDecision::class, 'review_decision_id');
    }
}
