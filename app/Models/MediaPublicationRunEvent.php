<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MediaPublicationRunEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'action',
        'details',
        'from_state',
        'to_state',
    ];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    protected static function booted(): void
    {
        self::updating(fn () => throw new \LogicException('Run audit evidence is append-only.'));
        self::deleting(fn () => throw new \LogicException('Run audit evidence cannot be deleted.'));
    }

    // Eloquent foreign-key relationships; keyed to the actual database columns.

    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

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
