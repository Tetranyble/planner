<?php

namespace App\Models;

use App\Concerns\Searchable;
use App\Concerns\Uuidable;
use App\Enums\Currency;
use App\Enums\DateFormat;
use App\Enums\DayOfWeek;
use App\Enums\Locale;
use App\Enums\TimeFormat;
use Database\Factories\OrganisationPreferenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganisationPreference extends Model
{
    /** @use HasFactory<OrganisationPreferenceFactory> */
    use HasFactory, Searchable, Uuidable;

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected $fillable = [
        'default_locale',
        'reporting_currency',
        'week_starts_on',
        'date_display_format',
        'time_display_format',
        'organisation_id',
    ];

    protected $attributes = [
        'default_locale' => Locale::EN_NG->value,
        'reporting_currency' => Currency::NGN->value,
        'week_starts_on' => DayOfWeek::MONDAY->value,
        'date_display_format' => DateFormat::DMY_SLASH->value,
        'time_display_format' => TimeFormat::TIME_24_HOUR->value,
    ];

    protected $casts = [
        'default_locale' => Locale::class,
        'reporting_currency' => Currency::class,
        'week_starts_on' => DayOfWeek::class,
        'date_display_format' => DateFormat::class,
        'time_display_format' => TimeFormat::class,
    ];

    // ---------- Relationships ----------

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }
}
