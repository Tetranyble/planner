<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MediaPublicationStageAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'finished_at',
        'operation',
        'outcome_code',
        'stage_sequence',
        'state',
    ];

    protected function casts(): array
    {
        return ['finished_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        self::deleting(fn () => throw new \LogicException('Media stage attempts cannot be deleted.'));
    }

    // Eloquent foreign-key relationships; keyed to the actual database columns.

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function mediaPublicationRun(): BelongsTo
    {
        return $this->belongsTo(MediaPublicationRun::class, 'media_publication_run_id');
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }
}
