<?php

namespace App\Models;

use Database\Factories\PublicationReconciliationEvidenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PublicationReconciliationEvidence extends Model
{
    use HasFactory;

    protected $table = 'publication_reconciliation_evidence';

    protected $fillable = [

        'brand_id',

        'evidence_kind',

        'evidence_sha256',

        'external_reference',

        'organisation_id',

        'publication_job_id',

        'submitted_by',

        'summary',

    ];

    protected static function newFactory(): PublicationReconciliationEvidenceFactory
    {
        return PublicationReconciliationEvidenceFactory::new();
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

    public function publicationJob(): BelongsTo
    {
        return $this->belongsTo(PublicationJob::class, 'publication_job_id');
    }
}
