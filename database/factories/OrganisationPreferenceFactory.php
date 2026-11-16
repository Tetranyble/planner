<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Enums\DateFormat;
use App\Enums\DayOfWeek;
use App\Enums\Locale;
use App\Enums\TimeFormat;
use App\Models\Organisation;
use App\Models\OrganisationPreference;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganisationPreference>
 */
class OrganisationPreferenceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => (string) str()->uuid(),
            'organisation_id' => Organisation::factory(),
            'default_locale' => Locale::EN_NG->value,
            'reporting_currency' => Currency::NGN->value,
            'week_starts_on' => DayOfWeek::MONDAY->value,
            'date_display_format' => DateFormat::DMY_SLASH->value,
            'time_display_format' => TimeFormat::TIME_24_HOUR->value,
        ];
    }

    // ---------- States ----------

    public function forOrganisation(Organisation|int $org): static
    {
        return $this->state(fn () => [
            'organisation_id' => $org instanceof Organisation ? $org->id : $org,
        ]);
    }

    public function locale(Locale $locale): static
    {
        return $this->state(fn () => ['default_locale' => $locale->value]);
    }

    public function currency(Currency $currency): static
    {
        return $this->state(fn () => ['reporting_currency' => $currency->value]);
    }

    public function weekStartsOn(DayOfWeek $day): static
    {
        return $this->state(fn () => ['week_starts_on' => $day->value]);
    }

    public function dateFormat(DateFormat $format): static
    {
        return $this->state(fn () => ['date_display_format' => $format->value]);
    }

    public function timeFormat(TimeFormat $format): static
    {
        return $this->state(fn () => ['time_display_format' => $format->value]);
    }
}
