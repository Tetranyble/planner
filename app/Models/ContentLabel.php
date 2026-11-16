<?php

namespace App\Models;

use App\Concerns\Uuidable;
use Database\Factories\ContentLabelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ContentLabel extends Model
{
    /** Explicitly deny mass assignment by default. Trusted repositories use forceFill. */
    protected $fillable = [];

    /** @use HasFactory<ContentLabelFactory> */
    use HasFactory, Uuidable;

    // Eloquent foreign-key relationships; keyed to the actual database columns.

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    public function contentItems(): BelongsToMany
    {
        return $this->belongsToMany(ContentItem::class, 'content_item_labels')->withPivot(['organisation_id', 'brand_id'])->withTimestamps();
    }
}
