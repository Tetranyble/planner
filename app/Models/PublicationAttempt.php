<?php

namespace App\Models;

use App\Concerns\Uuidable;
use Database\Factories\PublicationAttemptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicationAttempt extends Model
{
    /** Explicitly deny mass assignment by default. Trusted repositories use forceFill. */
    protected $fillable = [];

    /** @use HasFactory<PublicationAttemptFactory> */
    use HasFactory, Uuidable;

    protected function casts(): array
    {
        return ['response_redacted' => 'array', 'started_at' => 'immutable_datetime', 'finished_at' => 'immutable_datetime'];
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
