<?php

namespace App\Models;

use App\Concerns\Uuidable;
use Database\Factories\MediaPublicationRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class MediaPublicationRun extends Model
{
    /** @use HasFactory<MediaPublicationRunFactory> */
    use HasFactory, Uuidable;

    protected $fillable = [

        'armed_by_user_id',

        'blocker_code',

        'lease_owner',

        'lease_until',

        'next_check_at',

        'next_operation',

        'poll_count',

        'provider',

        'provider_operation_reference',

        'stage_sequence',

        'state',

        'verified_post_reference',

        'version',

    ];

    protected function casts(): array
    {
        return ['provider_operation_reference' => 'encrypted', 'verified_post_reference' => 'encrypted', 'lease_until' => 'immutable_datetime',
            'next_check_at' => 'immutable_datetime', 'version' => 'integer', 'stage_sequence' => 'integer', 'poll_count' => 'integer'];
    }

    public function events(): HasMany
    {
        return $this->hasMany(MediaPublicationRunEvent::class, 'media_publication_run_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(MediaPublicationStageAttempt::class, 'media_publication_run_id');
    }

    // Eloquent foreign-key relationships; keyed to the actual database columns.

    public function armedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'armed_by_user_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function mediaPublicationPreparation(): BelongsTo
    {
        return $this->belongsTo(MediaPublicationPreparation::class, 'media_publication_preparation_id');
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
