<?php

namespace Tests\Unit\Models;

use App\Enums\StatusEnum;
use App\Enums\TimeZone;
use App\Models\Organisation;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrganisationTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------
    // Schema
    // ---------------------------------------------------------------

    public function test_it_has_required_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('organisations', [
            'id',
            'uuid',
            'name',
            'timezone',
            'status',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_uuid_is_unique(): void
    {
        $org = Organisation::factory()->create();

        $this->expectException(QueryException::class);

        Organisation::factory()->create(['uuid' => $org->uuid]);
    }

    // ---------------------------------------------------------------
    // Model behavior
    // ---------------------------------------------------------------

    public function test_it_auto_generates_a_uuid_on_create(): void
    {
        $org = Organisation::factory()->create(['uuid' => null]);

        $this->assertNotNull($org->uuid);
        $this->assertTrue(Str::isUuid($org->uuid));
    }

    public function test_primary_key_is_incrementing_bigint(): void
    {
        $org = Organisation::factory()->create();

        $this->assertIsInt($org->id);
        $this->assertTrue($org->incrementing);
        $this->assertSame('int', $org->getKeyType());
    }

    public function test_route_key_is_uuid(): void
    {
        $this->assertSame('uuid', (new Organisation)->getRouteKeyName());
    }

    public function test_it_casts_enums(): void
    {
        $org = Organisation::factory()->create();

        $this->assertInstanceOf(TimeZone::class, $org->timezone);
        $this->assertInstanceOf(StatusEnum::class, $org->status);
    }

    public function test_it_casts_timestamps_to_datetime(): void
    {
        $org = Organisation::factory()->create();

        $this->assertInstanceOf(Carbon::class, $org->created_at);
        $this->assertInstanceOf(Carbon::class, $org->updated_at);
    }

    public function test_active_scope_filters_by_status(): void
    {
        Organisation::factory()->count(2)->create();
        Organisation::factory()->suspended()->create();
        Organisation::factory()->closed()->create();

        $this->assertSame(2, Organisation::active()->count());
        $this->assertSame(1, Organisation::suspended()->count());
        $this->assertSame(1, Organisation::closed()->count());
    }

    public function test_is_active_helper(): void
    {
        $this->assertTrue(Organisation::factory()->make(['status' => StatusEnum::ACTIVE->value])->isActive());
        $this->assertFalse(Organisation::factory()->make(['status' => StatusEnum::SUSPENDED->value])->isActive());
        $this->assertFalse(Organisation::factory()->make(['status' => StatusEnum::CLOSED->value])->isActive());
    }

    public function test_factory_states_apply_expected_values(): void
    {
        $suspended = Organisation::factory()->suspended()->create();
        $closed = Organisation::factory()->closed()->create();
        $london = Organisation::factory()->inTimezone(TimeZone::EUROPE_LONDON)->create();

        $this->assertSame(StatusEnum::SUSPENDED, $suspended->status);
        $this->assertSame(StatusEnum::CLOSED, $closed->status);
        $this->assertSame(TimeZone::EUROPE_LONDON, $london->timezone);
    }
}
