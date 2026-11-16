<?php

namespace Tests\Unit\Models;

use App\Models\City;
use App\Models\Country;
use App\Models\State;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class CountryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_has_required_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('countries', [
            'id',
            'uuid',
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
            'created_at',
            'updated_at',
            'deleted_at',
        ]));
    }

    public function test_it_can_create_a_country(): void
    {
        $country = Country::factory()->create(['name' => 'Nigeria']);

        $this->assertDatabaseHas('countries', ['name' => 'Nigeria']);
        $this->assertInstanceOf(Country::class, $country);
    }

    public function test_it_generates_a_uuid_on_creation(): void
    {
        $country = Country::factory()->create();

        $this->assertNotNull($country->uuid);
        $this->assertTrue(Str::isUuid($country->uuid));
    }

    public function test_uuid_is_mass_assignable_via_trait(): void
    {
        $this->assertContains('uuid', (new Country)->getFillable());
    }

    public function test_it_uses_uuid_as_route_key(): void
    {
        $country = Country::factory()->create();

        $this->assertEquals('uuid', $country->getRouteKeyName());
    }

    public function test_it_soft_deletes(): void
    {
        $country = Country::factory()->create();
        $country->delete();

        $this->assertSoftDeleted('countries', ['id' => $country->id]);
        $this->assertNull(Country::find($country->id));
        $this->assertNotNull(Country::withTrashed()->find($country->id));
    }

    public function test_it_casts_latitude_and_longitude_to_decimal(): void
    {
        $country = Country::factory()->create([
            'latitude' => '9.0820000000',
            'longitude' => '8.6753000000',
        ]);

        $this->assertEquals('9.0820000000', $country->latitude);
        $this->assertEquals('8.6753000000', $country->longitude);
    }

    public function test_it_has_many_states(): void
    {
        $country = Country::factory()->create();
        State::factory()->count(3)->create(['country_id' => $country->id]);

        $this->assertCount(3, $country->states);
        $this->assertInstanceOf(State::class, $country->states->first());
    }

    public function test_it_has_many_cities(): void
    {
        $country = Country::factory()->create();
        City::factory()->count(2)->create(['country_id' => $country->id]);

        $this->assertCount(2, $country->cities);
    }

    public function test_nullable_columns_accept_null(): void
    {
        $country = Country::factory()->create([
            'iso3' => null,
            'iso2' => null,
            'latitude' => null,
            'longitude' => null,
        ]);

        $this->assertNull($country->fresh()->iso3);
        $this->assertNull($country->fresh()->latitude);
    }

    public function test_name_is_required(): void
    {
        $this->expectException(QueryException::class);

        Country::factory()->create(['name' => null]);
    }
}
