<?php

namespace App\Models;

use Database\Factories\ContentVersionMediaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentVersionMedia extends Model
{
    protected $table = 'content_version_media';

    /** @use HasFactory<ContentVersionMediaFactory> */
    use HasFactory;

    public function asset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Content version media are append-only.'));
        static::deleting(fn () => throw new \LogicException('Content version media cannot be deleted.'));
    }

    protected $fillable = ['role', 'sort_order'];

    // Eloquent foreign-key relationships; keyed to the actual database columns.

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function contentVersion(): BelongsTo
    {
        return $this->belongsTo(ContentVersion::class, 'content_version_id');
    }

    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }
}
