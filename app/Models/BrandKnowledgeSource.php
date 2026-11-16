<?php

namespace App\Models;

use App\Concerns\Uuidable;
use Database\Factories\BrandKnowledgeSourceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrandKnowledgeSource extends Model
{
    /** Explicitly deny mass assignment by default. Trusted repositories use forceFill. */
    protected $fillable = [];

    /** @use HasFactory<BrandKnowledgeSourceFactory> */
    use HasFactory, Uuidable;

    // The package's existing knowledge-source migration intentionally has no timestamp columns.
    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(function (self $source): void {
            if ($source->getOriginal('trust_status') !== 'PENDING'
                && $source->isDirty(['title', 'source_type', 'source_url', 'content_text', 'content_hash', 'object_key'])) {
                throw new \LogicException('Reviewed sources must be versioned, not edited in place.');
            }
        });
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

    public function submittedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function verifiedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }
}
