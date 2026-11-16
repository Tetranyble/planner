<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SocialMetricSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [

        'account_reference',

        'clicks',

        'comments',

        'external_post_reference',

        'impressions',

        'likes',

        'observed_at',

        'provider',

        'reach',

        'shares',

        'source_reference',

        'source_type',

        'submission_key',

    ];

    protected function casts(): array
    {
        return ['observed_at' => 'immutable_datetime'];
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

    public function submittedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }
}
