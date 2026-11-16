<?php

namespace App\Models;

use Database\Factories\PublicationReconciliationDecisionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PublicationReconciliationDecision extends Model
{
    use HasFactory;

    protected $table = 'publication_reconciliation_decisions';

    protected $fillable = [

        'brand_id',

        'disposition',

        'evidence_id',

        'organisation_id',

        'publication_job_id',

        'rationale',

        'reviewed_by',

    ];

    protected static function newFactory(): PublicationReconciliationDecisionFactory
    {
        return PublicationReconciliationDecisionFactory::new();
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
