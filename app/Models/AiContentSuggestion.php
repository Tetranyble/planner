<?php

namespace App\Models;

use App\Concerns\Uuidable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AiContentSuggestion extends Model
{
    use HasFactory, Uuidable;

    protected $fillable = [

        'campaign_name',

        'model',

        'platform',

        'prompt_hash',

        'result_hash',

        'suggestion_text',

        'topic',

    ];

    protected function casts(): array
    {
        return ['promoted_at' => 'immutable_datetime'];
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

    public function promotedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'promoted_by_user_id');
    }

    public function promotedContentItem(): BelongsTo
    {
        return $this->belongsTo(ContentItem::class, 'promoted_content_item_id');
    }

    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }
}
