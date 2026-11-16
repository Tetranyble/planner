<?php

namespace App\Models;

use App\Concerns\Uuidable;
use Database\Factories\ProviderGrantReconciliationEvidenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Append-only evidence; never store OAuth secrets or raw provider user identities. */
final class ProviderGrantReconciliationEvidence extends Model
{
    /** @use HasFactory<ProviderGrantReconciliationEvidenceFactory> */
    use HasFactory, Uuidable;

    protected $table = 'provider_grant_reconciliation_evidence';

    public $timestamps = false;

    protected $fillable = ['organisation_id', 'revocation_request_id', 'submitted_by_user_id', 'source_type',
        'reference', 'summary', 'evidence_digest', 'created_at'];

    protected $hidden = ['evidence_digest'];

    protected $casts = ['created_at' => 'immutable_datetime'];

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new LogicException('Reconciliation evidence is append-only.');
        }

        return parent::save($options);
    }

    public function delete(): ?bool
    {
        throw new LogicException('Reconciliation evidence may not be removed through Eloquent.');
    }

    // Eloquent foreign-key relationships; keyed to the actual database columns.

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    public function submittedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }
}
