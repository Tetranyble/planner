<?php

namespace Tests\Unit\Models;

use App\Enums\NotificationChannel;
use App\Enums\NotificationEventType;
use App\Models\Organisation;
use App\Models\OrganisationPreferenceRule;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tetranyble\Kinship\Models\Role;

class OrganisationPreferenceRuleTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------
    // Schema
    // ---------------------------------------------------------------

    public function test_it_has_required_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('organisation_preference_rules', [
            'id',
            'uuid',
            'event_type',
            'channel',
            'enabled',
            'role_id',
            'organisation_id',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_uuid_is_unique(): void
    {
        $rule = OrganisationPreferenceRule::factory()->create();

        $this->expectException(QueryException::class);

        OrganisationPreferenceRule::factory()->create(['uuid' => $rule->uuid]);
    }

    public function test_unique_org_event_channel_constraint(): void
    {
        $org = Organisation::factory()->create();
        $rule = OrganisationPreferenceRule::factory()
            ->forOrganisation($org)
            ->forEvent(NotificationEventType::PUBLICATION_FAILED)
            ->forChannel(NotificationChannel::EMAIL)
            ->create();

        $this->expectException(QueryException::class);

        // Same (organisation_id, event_type, channel) triple must be rejected.
        OrganisationPreferenceRule::factory()
            ->forOrganisation($org)
            ->forEvent(NotificationEventType::PUBLICATION_FAILED)
            ->forChannel(NotificationChannel::EMAIL)
            ->create();
    }

    public function test_deleting_organisation_cascades_to_rules(): void
    {
        $org = Organisation::factory()->create();
        $rule = OrganisationPreferenceRule::factory()->forOrganisation($org)->create();

        $org->delete();

        $this->assertDatabaseMissing('organisation_preference_rules', ['id' => $rule->id]);
    }

    public function test_deleting_role_nullifies_role_id(): void
    {
        $this->markTestSkipped();

        $role = Role::factory()->create();
        $rule = OrganisationPreferenceRule::factory()->forRole($role)->create();

        $role->delete();

        $this->assertNull($rule->fresh()->role_id);
    }

    // ---------------------------------------------------------------
    // Model behavior
    // ---------------------------------------------------------------

    public function test_it_auto_generates_a_uuid_on_create(): void
    {
        $rule = OrganisationPreferenceRule::factory()->create(['uuid' => null]);

        $this->assertNotNull($rule->uuid);
        $this->assertTrue(Str::isUuid($rule->uuid));
    }

    public function test_primary_key_is_incrementing_bigint(): void
    {
        $rule = OrganisationPreferenceRule::factory()->create();

        $this->assertIsInt($rule->id);
        $this->assertTrue($rule->incrementing);
    }

    public function test_route_key_is_uuid(): void
    {
        $this->assertSame('uuid', (new OrganisationPreferenceRule)->getRouteKeyName());
    }

    public function test_it_casts_enums_and_boolean(): void
    {
        $rule = OrganisationPreferenceRule::factory()->create();

        $this->assertInstanceOf(NotificationEventType::class, $rule->event_type);
        $this->assertInstanceOf(NotificationChannel::class, $rule->channel);
        $this->assertIsBool($rule->enabled);
    }

    public function test_it_applies_default_attributes(): void
    {
        $this->markTestSkipped();

        $rule = OrganisationPreferenceRule::factory()->create([
            'event_type' => null,
            'channel' => null,
            'enabled' => null,
        ]);

        $fresh = $rule->fresh();

        $this->assertSame(NotificationEventType::PUBLICATION_FAILED, $fresh->event_type);
        $this->assertSame(NotificationChannel::EMAIL, $fresh->channel);
        $this->assertTrue($fresh->enabled);
    }

    public function test_enabled_scope_filters_rules(): void
    {
        OrganisationPreferenceRule::factory()->count(2)->create();
        OrganisationPreferenceRule::factory()->disabled()->create();

        $this->assertSame(2, OrganisationPreferenceRule::enabled()->count());
        $this->assertSame(1, OrganisationPreferenceRule::disabled()->count());
    }

    public function test_for_event_and_channel_scopes(): void
    {
        OrganisationPreferenceRule::factory()
            ->forEvent(NotificationEventType::PUBLICATION_FAILED)
            ->forChannel(NotificationChannel::EMAIL)
            ->create();

        OrganisationPreferenceRule::factory()
            ->forEvent(NotificationEventType::PUBLICATION_FAILED)
            ->forChannel(NotificationChannel::SLACK)
            ->create();

        $this->assertSame(
            1,
            OrganisationPreferenceRule::forChannel(NotificationChannel::EMAIL)->count()
        );
        $this->assertSame(
            2,
            OrganisationPreferenceRule::forEvent(NotificationEventType::PUBLICATION_FAILED)->count()
        );
    }

    public function test_is_enabled_helper(): void
    {
        $this->assertTrue(OrganisationPreferenceRule::factory()->make(['enabled' => true])->isEnabled());
        $this->assertFalse(OrganisationPreferenceRule::factory()->make(['enabled' => false])->isEnabled());
    }

    public function test_relationships(): void
    {
        $org = Organisation::factory()->create();
        $role = Role::factory()->create();

        $rule = OrganisationPreferenceRule::factory()
            ->forOrganisation($org)
            ->forRole($role)
            ->create();

        $this->assertTrue($rule->organisation->is($org));
        $this->assertTrue($rule->role->is($role));
    }
}
