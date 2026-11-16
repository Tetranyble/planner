<?php

namespace App\Models;

use App\Concerns\Uuidable;
use Database\Factories\ContentItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ContentItem extends Model
{
    /** @use HasFactory<ContentItemFactory> */
    use HasFactory, Uuidable;

    protected $fillable = [

        'campaign_id',

        'content_kind',

        'content_series_id',

        'title',

    ];

    // Eloquent foreign-key relationships; keyed to the actual database columns.

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    public function contentSeries(): BelongsTo
    {
        return $this->belongsTo(ContentSeries::class, 'content_series_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(ContentLabel::class, 'content_item_labels')->withPivot(['organisation_id', 'brand_id'])->withTimestamps();
    }
}
