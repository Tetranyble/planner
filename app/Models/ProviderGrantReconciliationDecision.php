<?php

namespace App\Models;

use App\Concerns\Uuidable;
use Database\Factories\ProviderGrantReconciliationDecisionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Append-only human review, NOT an upstream provider acknowledgement. */
final class ProviderGrantReconciliationDecision extends Model
{
    /** @use HasFactory<ProviderGrantReconciliationDecisionFactory> */
    use HasFactory, Uuidable;

    protected $table = 'provider_grant_reconciliation_decisions';

    public $timestamps = false;

    protected $fillable = ['organisation_id', 'revocation_request_id', 'evidence_id', 'reviewed_by_user_id',
        'outcome', 'rationale', 'evidence_digest', 'created_at'];

    protected $hidden = ['evidence_digest'];

    protected $casts = ['created_at' => 'immutable_datetime'];

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new LogicException('Reconciliation decisions are append-only.');
        }

        return parent::save($options);
    }

    public function delete(): ?bool
    {
        throw new LogicException('Reconciliation decisions may not be removed through Eloquent.');
    }

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
