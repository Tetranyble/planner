<?php

namespace Tests\Integration\Membership;

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tetranyble\Kinship\Models\Role;

/** Real MySQL and Kinship integration checks; not equivalent to mocked feature tests. */
final class InvitationIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_outbox_write_and_invitation_are_atomic_when_role_is_invalid(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->postJson('/api/v1/organisations', ['name' => 'Workspace'])->assertCreated();
        $org = Organisation::query()->firstOrFail();
        $this->actingAs($owner)->postJson("/api/v1/organisations/{$org->uuid}/members/invitations", [
            'email' => 'somebody@example.com', 'role' => 'root',
        ])->assertUnprocessable();
        $this->assertDatabaseCount('organisation_invitations', 0);
        $this->assertDatabaseCount('outbox_events', 0);
    }

    public function test_reinviting_same_email_invalidates_original_token_without_duplicate_pending_rows(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->postJson('/api/v1/organisations', ['name' => 'Workspace'])->assertCreated();
        $org = Organisation::query()->firstOrFail();
        $url = "/api/v1/organisations/{$org->uuid}/members/invitations";

        $this->actingAs($owner)->postJson($url, ['email' => 'Test@Example.com', 'role' => 'viewer'])->assertCreated();
        $first = DB::table('organisation_invitations')->first();
        $this->actingAs($owner)->postJson($url, ['email' => 'test@example.com', 'role' => 'viewer'])->assertCreated();

        $this->assertDatabaseCount('organisation_invitations', 2);
        $this->assertDatabaseCount('outbox_events', 2);
        $this->assertNotNull(DB::table('organisation_invitations')->where('id', $first->id)->value('revoked_at'));
        $this->assertSame(1, DB::table('organisation_invitations')->whereNull('revoked_at')
            ->whereNull('accepted_at')->count());
    }

    public function test_foreign_role_from_different_workspace_cannot_be_accepted(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->postJson('/api/v1/organisations', ['name' => 'Workspace A'])->assertCreated();
        $first = Organisation::query()->latest('id')->firstOrFail();
        $this->actingAs($owner)->postJson('/api/v1/organisations', ['name' => 'Workspace B'])->assertCreated();
        $second = Organisation::query()->latest('id')->firstOrFail();
        $recipient = User::factory()->create(['email' => 'recipient@example.com']);

        $this->actingAs($owner)->postJson("/api/v1/organisations/{$first->uuid}/members/invitations", [
            'email' => $recipient->email, 'role' => 'viewer',
        ])->assertCreated();
        $payload = json_decode(DB::table('outbox_events')->orderByDesc('id')->value('payload'), true);
        $token = Crypt::decryptString($payload['encrypted_token']);
        $foreignRole = Role::query()->forWorkspace($second->id)->where('name', 'owner')->firstOrFail();
        DB::table('organisation_invitations')->update(['role_id' => $foreignRole->id]);

        $this->actingAs($recipient)->postJson('/api/v1/invitations/accept', ['token' => $token])->assertStatus(409);
        $this->assertDatabaseMissing('organisation_memberships', [
            'organisation_id' => $first->id, 'user_id' => $recipient->id,
        ]);
    }

    public function test_different_tenant_owner_cannot_view_or_mutate_members(): void
    {
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();
        $this->actingAs($ownerA)->postJson('/api/v1/organisations', ['name' => 'A'])->assertCreated();
        $orgA = Organisation::query()->latest('id')->firstOrFail();
        $this->actingAs($ownerB)->postJson('/api/v1/organisations', ['name' => 'B'])->assertCreated();
        $orgB = Organisation::query()->latest('id')->firstOrFail();

        $this->actingAs($ownerA)->getJson("/api/v1/organisations/{$orgB->uuid}/members")->assertForbidden();
        $this->actingAs($ownerA)->patchJson("/api/v1/organisations/{$orgB->uuid}/members/{$ownerB->id}/role", [
            'role' => 'viewer',
        ])->assertForbidden();
        $this->actingAs($ownerB)->getJson("/api/v1/organisations/{$orgA->uuid}/members")->assertForbidden();
    }
}
