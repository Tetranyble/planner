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

class StateTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_has_required_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('states', [
            'id',
            'uuid',
            'name',
            'country_id',
            'country_code',
            'country_name',
            'state_code',
            'type',
            'latitude',
            'longitude',
            'created_at',
            'updated_at',
            'deleted_at',
        ]));
    }

    public function test_it_can_create_a_state(): void
    {
        $state = State::factory()->create(['name' => 'Lagos']);

        $this->assertDatabaseHas('states', ['name' => 'Lagos']);
        $this->assertInstanceOf(State::class, $state);
    }

    public function test_it_generates_a_uuid_on_creation(): void
    {
        $state = State::factory()->create();

        $this->assertNotNull($state->uuid);
        $this->assertTrue(Str::isUuid($state->uuid));
    }

    public function test_it_uses_uuid_as_route_key(): void
    {
        $state = State::factory()->create();

        $this->assertEquals('uuid', $state->getRouteKeyName());
    }

    public function test_it_belongs_to_a_country(): void
    {
        $country = Country::factory()->create();
        $state = State::factory()->forCountry($country)->create();

        $this->assertInstanceOf(Country::class, $state->country);
        $this->assertEquals($country->id, $state->country->id);
    }

    public function test_it_has_many_cities(): void
    {
        $state = State::factory()->create();
        City::factory()->count(3)->create(['state_id' => $state->id]);

        $this->assertCount(3, $state->cities);
    }

    public function test_it_soft_deletes(): void
    {
        $state = State::factory()->create();
        $state->delete();

        $this->assertSoftDeleted('states', ['id' => $state->id]);
    }

    public function test_deleting_country_sets_null_on_state(): void
    {
        $country = Country::factory()->create();
        $state = State::factory()->forCountry($country)->create();

        $country->forceDelete();

        $this->assertNull($state->fresh()->country_id);
    }

    public function test_name_is_required(): void
    {
        $this->expectException(QueryException::class);

        State::factory()->create(['name' => null]);
    }

    public function test_state_can_be_created(): void
    {
        $state = State::factory()->create([
            'name' => 'Badakhshan',
            'country_code' => 'AF',
            'country_name' => 'Afghanistan',
            'state_code' => 'BDS',
            'type' => 'province',
            'latitude' => 36.7347725,
            'longitude' => 70.8119953,
        ]);

        $this->assertDatabaseHas('states', [
            'name' => 'Badakhshan',
            'country_code' => 'AF',
        ]);
    }
}
