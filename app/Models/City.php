<?php

namespace App\Models;

use App\Concerns\Searchable;
use App\Concerns\Uuidable;
use Database\Factories\CityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class City extends Model
{
    /** @use HasFactory<CityFactory> */
    use HasFactory, Searchable, SoftDeletes, Uuidable;

    protected $fillable = [
        'name',
        'state_id',
        'state_code',
        'state_name',
        'country_id',
        'country_code',
        'country_name',
        'latitude',
        'longitude',
        'wikiDataId',
    ];

    protected $casts = [
        'latitude' => 'decimal:10',
        'longitude' => 'decimal:10',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class, 'state_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }
}
