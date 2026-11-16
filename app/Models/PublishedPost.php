<?php

namespace App\Models;

use Database\Factories\PublishedPostFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublishedPost extends Model
{
    /** @use HasFactory<PublishedPostFactory> */
    use HasFactory;

    protected $fillable = [

        'external_post_id',

        'external_url',

        'last_checked_at',

        'provider',

        'published_at',

        'remote_status',

    ];

    protected function casts(): array
    {
        return ['published_at' => 'immutable_datetime', 'last_checked_at' => 'immutable_datetime'];
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

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'social_account_id');
    }
}
