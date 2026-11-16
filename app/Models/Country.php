<?php

namespace App\Models;

use App\Concerns\Searchable;
use App\Concerns\Uuidable;
use Database\Factories\CountryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Country extends Model
{
    /** @use HasFactory<CountryFactory> */
    use HasFactory, Searchable, SoftDeletes, Uuidable;

    protected $fillable = [
        'name',
        'iso3',
        'iso2',
        'numeric_code',
        'phone_code',
        'capital',
        'currency',
        'currency_name',
        'currency_symbol',
        'tld',
        'native',
        'region',
        'subregion',
        'latitude',
        'longitude',
        'emoji',
        'emojiU',
    ];

    protected $casts = [
        'latitude' => 'decimal:10',
        'longitude' => 'decimal:10',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function states(): HasMany
    {
        return $this->hasMany(State::class, 'country_id');
    }

    public function cities(): HasMany
    {
        return $this->hasMany(City::class, 'country_id');
    }
}
