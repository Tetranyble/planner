<?php

namespace App\Models;

use App\Concerns\Uuidable;
use Database\Factories\ContentVersionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentVersion extends Model
{
    /** @use HasFactory<ContentVersionFactory> */
    use HasFactory, Uuidable;

    protected $fillable = [

        'ai_generated',

        'body_json',

        'content_hash',

        'copy_text',

        'includes_sensitive_claim',

        'source_ai_run_id',

    ];

    protected function casts(): array
    {
        return ['body_json' => 'array', 'version_number' => 'integer', 'ai_generated' => 'boolean', 'includes_sensitive_claim' => 'boolean'];
    }

    public function mediaLinks(): HasMany
    {
        return $this->hasMany(ContentVersionMedia::class, 'content_version_id');
    }

    /**
     * Eloquent events may be faked/disabled by tests and framework integrations.
     * Ensure the append-only rule still holds when saving an existing version.
     */
    protected function performUpdate(Builder $query)
    {
        throw new \LogicException('Content history must be append-only.');
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Content history must be append-only.'));
        static::deleting(fn () => throw new \LogicException('Content history cannot be deleted.'));
    }

    // Eloquent foreign-key relationships; keyed to the actual database columns.

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function contentItem(): BelongsTo
    {
        return $this->belongsTo(ContentItem::class, 'content_item_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }
}
