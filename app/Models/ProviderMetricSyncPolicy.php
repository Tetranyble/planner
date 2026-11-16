<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProviderMetricSyncPolicy extends Model
{
    use HasFactory;

    protected $fillable = [

        'enabled',

        'last_queued_day',

        'max_posts_per_day',

        'utc_hour',

        'version',

    ];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'last_queued_day' => 'immutable_date'];
    }

    // Eloquent foreign-key relationships; keyed to the actual database columns.

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function configuredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'configured_by_user_id');
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }
}
