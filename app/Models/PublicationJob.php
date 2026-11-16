<?php

namespace App\Models;

use Database\Factories\PublicationJobFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicationJob extends Model
{
    /** @use HasFactory<PublicationJobFactory> */
    use HasFactory;

    protected $fillable = [

        'armed_at',

        'armed_by_user_id',

        'attempt_count',

        'due_state_marker',

        'last_error_code',

        'last_error_message',

        'lease_owner',

        'lease_state_marker',

        'lease_until',

        'max_attempts',

        'next_retry_at',

        'occurrence_key',

        'run_at',

        'state',

        'version',

    ];

    protected function casts(): array
    {
        return ['run_at' => 'immutable_datetime', 'lease_until' => 'immutable_datetime', 'next_retry_at' => 'immutable_datetime'];
    }

    // Eloquent foreign-key relationships; keyed to the actual database columns.

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class, 'approval_request_id');
    }

    public function armedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'armed_by_user_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function contentTarget(): BelongsTo
    {
        return $this->belongsTo(ContentTarget::class, 'content_target_id');
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }
}
