<?php

namespace App\Models;

use Database\Factories\MediaTikTokConsentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MediaTikTokConsent extends Model
{
    protected $table = 'media_tiktok_consents';

    /** @use HasFactory<MediaTikTokConsentFactory> */
    use HasFactory;

    protected $fillable = [

        'approval_payload_hash',

        'grant_fingerprint',

        'media_sha256',

        'privacy_selection',

        'verified_at',

        'verified_creator_options',

    ];

    protected function casts(): array
    {
        return ['privacy_selection' => 'array', 'verified_creator_options' => 'array', 'verified_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        self::updating(fn () => throw new \LogicException('TikTok consent evidence is immutable.'));
        self::deleting(fn () => throw new \LogicException('TikTok consent evidence cannot be deleted.'));
    }

    // Eloquent foreign-key relationships; keyed to the actual database columns.

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function consentingUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consenting_user_id');
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
