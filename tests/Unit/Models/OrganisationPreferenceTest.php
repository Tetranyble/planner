<?php

namespace Tests\Unit\Models;

use App\Enums\Currency;
use App\Enums\DateFormat;
use App\Enums\DayOfWeek;
use App\Enums\Locale;
use App\Enums\TimeFormat;
use App\Models\Organisation;
use App\Models\OrganisationPreference;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrganisationPreferenceTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------
    // Schema
    // ---------------------------------------------------------------

    public function test_it_has_required_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('organisation_preferences', [
            'id',
            'uuid',
            'default_locale',
            'reporting_currency',
            'week_starts_on',
            'date_display_format',
            'time_display_format',
            'organisation_id',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_uuid_is_unique(): void
    {
        $pref = OrganisationPreference::factory()->create();

        $this->expectException(QueryException::class);

        OrganisationPreference::factory()->create(['uuid' => $pref->uuid]);
    }

    public function test_organisation_id_is_unique(): void
    {
        $org = Organisation::factory()->create();

        OrganisationPreference::factory()->forOrganisation($org)->create();

        $this->expectException(QueryException::class);

        OrganisationPreference::factory()->forOrganisation($org)->create();
    }

    public function test_deleting_organisation_cascades_to_preferences(): void
    {
        $org = Organisation::factory()->create();
        $pref = OrganisationPreference::factory()->forOrganisation($org)->create();

        $org->delete();

        $this->assertDatabaseMissing('organisation_preferences', ['id' => $pref->id]);
    }

    // ---------------------------------------------------------------
    // Model behavior
    // ---------------------------------------------------------------

    public function test_it_auto_generates_a_uuid_on_create(): void
    {
        $pref = OrganisationPreference::factory()->create(['uuid' => null]);

        $this->assertNotNull($pref->uuid);
        $this->assertTrue(Str::isUuid($pref->uuid));
    }

    public function test_primary_key_is_incrementing_bigint(): void
    {
        $pref = OrganisationPreference::factory()->create();

        $this->assertIsInt($pref->id);
        $this->assertTrue($pref->incrementing);
    }

    public function test_route_key_is_uuid(): void
    {
        $this->assertSame('uuid', (new OrganisationPreference)->getRouteKeyName());
    }

    public function test_it_casts_all_enums(): void
    {
        $pref = OrganisationPreference::factory()->create();

        $this->assertInstanceOf(Locale::class, $pref->default_locale);
        $this->assertInstanceOf(Currency::class, $pref->reporting_currency);
        $this->assertInstanceOf(DayOfWeek::class, $pref->week_starts_on);
        $this->assertInstanceOf(DateFormat::class, $pref->date_display_format);
        $this->assertInstanceOf(TimeFormat::class, $pref->time_display_format);
    }

    public function test_it_casts_timestamps_to_datetime(): void
    {
        $pref = OrganisationPreference::factory()->create();

        $this->assertInstanceOf(Carbon::class, $pref->created_at);
        $this->assertInstanceOf(Carbon::class, $pref->updated_at);
    }

    public function test_it_applies_default_attributes(): void
    {
        $pref = OrganisationPreference::factory()->create([
            //            'default_locale'      => null,
            //            'reporting_currency'  => null,
            //            'week_starts_on'      => null,
            //            'date_display_format' => null,
            //            'time_display_format' => null,
        ]);

        $fresh = $pref->fresh();

        $this->assertSame(Locale::EN_NG, $fresh->default_locale);
        $this->assertSame(Currency::NGN, $fresh->reporting_currency);
        $this->assertSame(DayOfWeek::MONDAY, $fresh->week_starts_on);
        $this->assertSame(DateFormat::DMY_SLASH, $fresh->date_display_format);
        $this->assertSame(TimeFormat::TIME_24_HOUR, $fresh->time_display_format);
    }

    public function test_relationship_to_organisation(): void
    {
        $org = Organisation::factory()->create();
        $pref = OrganisationPreference::factory()->forOrganisation($org)->create();

        $this->assertTrue($pref->organisation->is($org));
    }

    public function test_organisation_has_one_preferences(): void
    {
        $org = Organisation::factory()->create();
        $pref = OrganisationPreference::factory()->forOrganisation($org)->create();

        $this->assertTrue($org->preferences->is($pref));
    }

    public function test_factory_states_apply_expected_values(): void
    {
        $pref = OrganisationPreference::factory()
            ->locale(Locale::EN_US)
            ->currency(Currency::USD)
            ->weekStartsOn(DayOfWeek::SUNDAY)
            ->dateFormat(DateFormat::MDY_SLASH)
            ->timeFormat(TimeFormat::TIME_12_HOUR)
            ->create();

        $this->assertSame(Locale::EN_US, $pref->default_locale);
        $this->assertSame(Currency::USD, $pref->reporting_currency);
        $this->assertSame(DayOfWeek::SUNDAY, $pref->week_starts_on);
        $this->assertSame(DateFormat::MDY_SLASH, $pref->date_display_format);
        $this->assertSame(TimeFormat::TIME_12_HOUR, $pref->time_display_format);
    }
}
