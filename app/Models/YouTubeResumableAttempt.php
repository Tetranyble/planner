<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class YouTubeResumableAttempt extends Model
{
    use HasFactory;

    protected $table = 'youtube_resumable_attempts';

    protected $fillable = [

        'action',

        'finished_at',

        'offset_bytes',

        'outcome_code',

        'sequence',

        'state',

    ];

    protected function casts(): array
    {
        return ['finished_at' => 'immutable_datetime'];
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
