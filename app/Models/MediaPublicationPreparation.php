<?php

namespace App\Models;

use App\Concerns\Uuidable;
use Database\Factories\MediaPublicationPreparationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MediaPublicationPreparation extends Model
{
    /** @use HasFactory<MediaPublicationPreparationFactory> */
    use HasFactory, Uuidable;

    protected $fillable = [

        'approval_payload_hash',

        'blocker_code',

        'media_sha256',

        'protocol',

        'protocol_manifest',

        'provider',

        'state',

        'version',

    ];

    protected function casts(): array
    {
        return ['protocol_manifest' => 'array', 'version' => 'integer'];
    }

    public function events(): HasMany
    {
        return $this->hasMany(MediaPublicationPreparationEvent::class, 'media_publication_preparation_id');
    }

    // Eloquent foreign-key relationships; keyed to the actual database columns.

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    public function preparedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by_user_id');
    }

    public function publicationJob(): BelongsTo
    {
        return $this->belongsTo(PublicationJob::class, 'publication_job_id');
    }
}
