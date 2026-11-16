<?php

namespace App\Models;

use App\Concerns\Uuidable;
use Database\Factories\ContentTargetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentTarget extends Model
{
    /** @use HasFactory<ContentTargetFactory> */
    use HasFactory, Uuidable;

    protected $fillable = [

        'created_by_user_id',

        'payload_hash',

        'payload_json',

        'platform_caption',

    ];

    public function version(): BelongsTo
    {
        return $this->belongsTo(ContentVersion::class, 'content_version_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'social_account_id');
    }

    protected function casts(): array
    {
        return ['payload_json' => 'array'];
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

    public function contentVersion(): BelongsTo
    {
        return $this->belongsTo(ContentVersion::class, 'content_version_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'social_account_id');
    }
}
