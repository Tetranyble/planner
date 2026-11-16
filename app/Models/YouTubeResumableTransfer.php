<?php

namespace App\Models;

use App\Concerns\Uuidable;
use Database\Factories\YouTubeResumableTransferFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class YouTubeResumableTransfer extends Model
{
    protected $table = 'youtube_resumable_transfers';

    /** @use HasFactory<YouTubeResumableTransferFactory> */
    use HasFactory, Uuidable;

    protected $fillable = [

        'armed_by_user_id',

        'blocker_code',

        'canonical_mime',

        'canonical_sha256',

        'committed_bytes',

        'lease_owner',

        'lease_until',

        'next_action',

        'next_check_at',

        'poll_count',

        'sequence',

        'session_url_ciphertext',

        'state',

        'total_bytes',

        'version',

        'video_id_ciphertext',

    ];

    protected $hidden = ['session_url_ciphertext', 'video_id_ciphertext', 'lease_owner'];

    protected function casts(): array
    {
        return [
            'session_url_ciphertext' => 'encrypted', 'video_id_ciphertext' => 'encrypted',
            'lease_until' => 'immutable_datetime', 'next_check_at' => 'immutable_datetime',
            'total_bytes' => 'integer', 'committed_bytes' => 'integer', 'version' => 'integer', 'sequence' => 'integer', 'poll_count' => 'integer',
        ];
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(YouTubeResumableAttempt::class, 'youtube_resumable_transfer_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(YouTubeResumableEvent::class, 'youtube_resumable_transfer_id');
    }

    // Eloquent foreign-key relationships; keyed to the actual database columns.

    public function armedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'armed_by_user_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
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
