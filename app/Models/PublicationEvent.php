<?php

namespace App\Models;

use App\Concerns\Uuidable;
use Database\Factories\PublicationEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicationEvent extends Model
{
    /** Explicitly deny mass assignment by default. Trusted repositories use forceFill. */
    protected $fillable = [];

    /** @use HasFactory<PublicationEventFactory> */
    use HasFactory, Uuidable;

    protected function casts(): array
    {
        return ['details' => 'array', 'occurred_at' => 'immutable_datetime'];
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
