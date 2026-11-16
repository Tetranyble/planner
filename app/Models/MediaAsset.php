<?php

namespace App\Models;

use App\Concerns\Uuidable;
use Database\Factories\MediaAssetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaAsset extends Model
{
    /** @use HasFactory<MediaAssetFactory> */
    use HasFactory, Uuidable;

    protected $fillable = [

        'byte_size',

        'created_by_ai_run_id',

        'created_by_user_id',

        'duration_ms',

        'file_name',

        'height',

        'mime_type',

        'object_key',

        'sha256',

        'source_type',

        'width',

    ];

    // Eloquent foreign-key relationships; keyed to the actual database columns.

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
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
