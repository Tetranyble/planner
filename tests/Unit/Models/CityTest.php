<?php

namespace Tests\Unit\Models;

use App\Models\City;
use App\Models\Country;
use App\Models\State;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class CityTest extends TestCase
{
    public function test_it_has_required_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('cities', [
            'id',
            'uuid',
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
            'created_at',
            'updated_at',
            'deleted_at',
        ]));
    }

    public function test_it_can_create_a_city(): void
    {
        $city = City::factory()->create(['name' => 'Ikeja']);

        $this->assertDatabaseHas('cities', ['name' => 'Ikeja']);
        $this->assertInstanceOf(City::class, $city);
    }

    public function test_it_generates_a_uuid_on_creation(): void
    {
        $city = City::factory()->create();

        $this->assertNotNull($city->uuid);
        $this->assertTrue(Str::isUuid($city->uuid));
    }

    public function test_it_uses_uuid_as_route_key(): void
    {
        $city = City::factory()->create();

        $this->assertEquals('uuid', $city->getRouteKeyName());
    }

    public function test_it_belongs_to_a_state(): void
    {
        $state = State::factory()->create();
        $city = City::factory()->forState($state)->create();

        $this->assertInstanceOf(State::class, $city->state);
        $this->assertEquals($state->id, $city->state->id);
    }

    public function test_it_belongs_to_a_country(): void
    {
        $country = Country::factory()->create();
        $city = City::factory()->forCountry($country)->create();

        $this->assertInstanceOf(Country::class, $city->country);
        $this->assertEquals($country->id, $city->country->id);
    }

    public function test_it_soft_deletes(): void
    {
        $city = City::factory()->create();
        $city->delete();

        $this->assertSoftDeleted('cities', ['id' => $city->id]);
        $this->assertNull(City::find($city->id));
        $this->assertNotNull(City::withTrashed()->find($city->id));
    }

    public function test_deleting_state_sets_null_on_city(): void
    {
        $state = State::factory()->create();
        $city = City::factory()->forState($state)->create();

        $state->forceDelete();

        $this->assertNull($city->fresh()->state_id);
    }

    public function test_deleting_country_sets_null_on_city(): void
    {
        $country = Country::factory()->create();
        $city = City::factory()->forCountry($country)->create();

        $country->forceDelete();

        $this->assertNull($city->fresh()->country_id);
    }

    public function test_it_casts_latitude_and_longitude_to_decimal(): void
    {
        $city = City::factory()->create([
            'latitude' => '6.5244000000',
            'longitude' => '3.3792000000',
        ]);

        $this->assertEquals('6.5244000000', $city->latitude);
        $this->assertEquals('3.3792000000', $city->longitude);
    }

    public function test_name_is_required(): void
    {
        $this->expectException(QueryException::class);

        City::factory()->create(['name' => null]);
    }

    public function test_country_code_is_required(): void
    {
        $this->expectException(QueryException::class);

        City::factory()->create(['country_code' => null]);
    }

    public function test_country_name_is_required(): void
    {
        $this->expectException(QueryException::class);

        City::factory()->create(['country_name' => null]);
    }

    public function test_nullable_columns_accept_null(): void
    {
        $city = City::factory()->create([
            'state_id' => null,
            'state_code' => null,
            'state_name' => null,
            'country_id' => null,
            'latitude' => null,
            'longitude' => null,
            'wikiDataId' => null,
        ]);

        $this->assertNull($city->fresh()->state_id);
        $this->assertNull($city->fresh()->latitude);
    }
}
