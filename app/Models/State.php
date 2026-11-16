<?php

namespace App\Models;

use App\Concerns\Searchable;
use App\Concerns\Uuidable;
use Database\Factories\StateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class State extends Model
{
    /** @use HasFactory<StateFactory> */
    use HasFactory, Searchable, SoftDeletes, Uuidable;

    protected $fillable = [
        'name',
        'country_id',
        'country_code',
        'country_name',
        'state_code',
        'type',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'latitude' => 'decimal:10',
        'longitude' => 'decimal:10',
        'deleted_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function cities(): HasMany
    {
        return $this->hasMany(City::class, 'state_id');
    }
}
