<?php

namespace Tests\Feature\Membership;

use App\Authorization\CurrentOrganisation;
use App\Enums\StateEnum;
use App\Mail\OrganisationInvitationMail;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** HTTP contract and security tests. Requires the installed Kinship package and real MySQL. */
final class OrganisationMembershipEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private function provision(User $owner): Organisation
    {
        $this->actingAs($owner)->postJson('/api/v1/organisations', [
            'name' => 'Pensuh parent organisation',
        ])->assertCreated();

        return Organisation::query()->latest('id')->firstOrFail();
    }

    private function invite(Organisation $org, User $owner, string $email, string $role = 'contributor'): string
    {
        $this->actingAs($owner)->postJson("/api/v1/organisations/{$org->uuid}/members/invitations", [
            'email' => $email, 'role' => $role,
        ])->assertCreated()->assertJsonMissingPath('data.token')->assertJsonMissingPath('data.token_hash');

        $payload = json_decode(DB::table('outbox_events')->where('event_type', 'organisation.invitation.created')
            ->orderByDesc('id')->value('payload'), true, 512, JSON_THROW_ON_ERROR);

        return Crypt::decryptString($payload['encrypted_token']);
    }

    public function test_invitation_requires_membership_permission_and_verified_sender(): void
    {
        $owner = User::factory()->create();
        $org = $this->provision($owner);
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->postJson("/api/v1/organisations/{$org->uuid}/members/invitations", [
            'email' => 'recipient@example.com', 'role' => 'owner',
        ])->assertForbidden();
        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified)->postJson("/api/v1/organisations/{$org->uuid}/members/invitations", [
            'email' => 'recipient@example.com', 'role' => 'owner',
        ])->assertStatus(409);
        $this->assertDatabaseCount('organisation_invitations', 0);
    }

    public function test_invitation_is_hashed_and_only_matching_verified_user_can_accept(): void
    {
        $owner = User::factory()->create();
        $org = $this->provision($owner);
        $invitee = User::factory()->create(['email' => 'recipient@example.com']);
        $token = $this->invite($org, $owner, 'RECIPIENT@example.com');

        $this->assertDatabaseHas('organisation_invitations', [
            'organisation_id' => $org->id, 'email' => 'recipient@example.com',
            'token_hash' => hash('sha256', $token),
        ]);
        $this->assertDatabaseMissing('organisation_invitations', ['token_hash' => $token]);
        $wrongUser = User::factory()->create();
        $this->actingAs($wrongUser)->postJson('/api/v1/invitations/accept', ['token' => $token])->assertForbidden();
        $this->actingAs($invitee)->postJson('/api/v1/invitations/accept', ['token' => $token])->assertOk();
        $this->assertDatabaseHas('organisation_memberships', [
            'organisation_id' => $org->id, 'user_id' => $invitee->id,
            'state' => StateEnum::ACTIVE->value, 'invited_by_user_id' => $owner->id,
        ]);
        app(CurrentOrganisation::class)->select($invitee, $org->id);
        $this->assertTrue($invitee->hasPermission('brand.index'));
        $this->actingAs($invitee)->postJson('/api/v1/invitations/accept', ['token' => $token])->assertStatus(410);
    }

    public function test_expired_or_cancelled_invitations_are_unusable(): void
    {
        $owner = User::factory()->create();
        $org = $this->provision($owner);
        $recipient = User::factory()->create(['email' => 'person@example.com']);
        $expiredToken = $this->invite($org, $owner, $recipient->email);
        DB::table('organisation_invitations')->update(['expires_at' => now()->subSecond()]);
        $this->actingAs($recipient)->postJson('/api/v1/invitations/accept', ['token' => $expiredToken])->assertStatus(410);

        $newToken = $this->invite($org, $owner, $recipient->email);
        $uuid = DB::table('organisation_invitations')->orderByDesc('id')->value('uuid');
        $this->actingAs($owner)->deleteJson("/api/v1/organisations/{$org->uuid}/members/invitations/{$uuid}")->assertOk();
        $this->actingAs($recipient)->postJson('/api/v1/invitations/accept', ['token' => $newToken])->assertStatus(410);
        $this->assertDatabaseMissing('organisation_memberships', ['organisation_id' => $org->id, 'user_id' => $recipient->id]);
    }

    public function test_last_owner_cannot_be_revoked_or_demoted(): void
    {
        $owner = User::factory()->create();
        $org = $this->provision($owner);
        $this->actingAs($owner)->patchJson("/api/v1/organisations/{$org->uuid}/members/{$owner->id}/role", [
            'role' => 'manager',
        ])->assertStatus(409);
        $this->actingAs($owner)->deleteJson("/api/v1/organisations/{$org->uuid}/members/{$owner->id}")
            ->assertStatus(409);
        $this->assertDatabaseHas('organisation_memberships', [
            'organisation_id' => $org->id, 'user_id' => $owner->id, 'state' => StateEnum::ACTIVE->value,
        ]);
    }

    public function test_owner_can_demote_second_owner_and_revoking_member_revokes_brand_access(): void
    {
        $owner = User::factory()->create();
        $org = $this->provision($owner);
        $second = User::factory()->create(['email' => 'second@example.com']);
        $token = $this->invite($org, $owner, $second->email, 'owner');
        $this->actingAs($second)->postJson('/api/v1/invitations/accept', ['token' => $token])->assertOk();
        $this->actingAs($owner)->patchJson("/api/v1/organisations/{$org->uuid}/members/{$second->id}/role", [
            'role' => 'contributor',
        ])->assertOk();

        $this->actingAs($owner)->postJson("/api/v1/organisations/{$org->uuid}/brands", [
            'name' => 'Pensuh',
        ])->assertCreated();
        $brand = Brand::query()->firstOrFail();
        $roleId = DB::table('organisation_memberships')->where('organisation_id', $org->id)
            ->where('user_id', $second->id)->value('role_id');
        DB::table('brand_memberships')->insert([
            'organisation_id' => $org->id, 'brand_id' => $brand->id, 'user_id' => $second->id,
            'role_id' => $roleId, 'state' => StateEnum::ACTIVE->value,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs($owner)->deleteJson("/api/v1/organisations/{$org->uuid}/members/{$second->id}")
            ->assertOk();
        $this->assertDatabaseHas('brand_memberships', [
            'organisation_id' => $org->id, 'brand_id' => $brand->id, 'user_id' => $second->id,
            'state' => StateEnum::REVOKED->value,
        ]);
        $this->assertDatabaseHas('organisation_memberships', [
            'organisation_id' => $org->id, 'user_id' => $second->id, 'state' => StateEnum::REVOKED->value,
        ]);
    }

    public function test_durable_dispatcher_sends_once_for_successful_outbox_event(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $org = $this->provision($owner);
        $this->invite($org, $owner, 'new@example.com');
        $this->artisan('planner:send-organisation-invitations', ['--limit' => 10])->assertSuccessful();
        Mail::assertSent(OrganisationInvitationMail::class, 1);
        $this->assertDatabaseHas('outbox_events', ['event_type' => 'organisation.invitation.created', 'state' => 'DISPATCHED']);
        $this->artisan('planner:send-organisation-invitations', ['--limit' => 10])->assertSuccessful();
        Mail::assertSent(OrganisationInvitationMail::class, 1);
    }
}
