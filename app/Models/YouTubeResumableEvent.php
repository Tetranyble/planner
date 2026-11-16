<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class YouTubeResumableEvent extends Model
{
    use HasFactory;

    protected $table = 'youtube_resumable_events';

    protected $fillable = [

        'action',

        'from_state',

        'safe_details',

        'to_state',

    ];

    protected function casts(): array
    {
        return ['safe_details' => 'array'];
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
}
