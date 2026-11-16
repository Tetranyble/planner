<?php

namespace App\Models;

use App\Concerns\Uuidable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BrandAiUsageReservation extends Model
{
    use HasFactory, Uuidable;

    protected $fillable = ['purpose'];

    protected function casts(): array
    {
        return ['usage_day' => 'immutable_date', 'expires_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime'];
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

    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }
}
