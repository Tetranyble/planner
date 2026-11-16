<?php

namespace Tests\Integration\Social;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\ProviderConnection;
use App\Models\ProviderConnectionCredential;
use App\Models\SocialAccount;
use App\Models\User;
use App\Repository\Contracts\ProviderGrantOwnershipRepositoryContract;
use App\Repository\Contracts\ProviderGrantReconciliationRepositoryContract;
use App\Repository\Contracts\ProviderGrantRevocationRepositoryContract;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

/** MySQL/InnoDB integration; no DB facade used for fixture creation. */
final class GrantReconciliationRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('social_integrations.providers.tiktok', [
            'client_id' => 'fixture-tiktok-app', 'client_secret' => 'fixture-tiktok-secret',
        ]);
        config()->set('social_integrations.grant_actor_hmac_key', 'fixture-grant-hmac');
    }

    private function case(string $state = 'UNCERTAIN'): array
    {
        $org = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();
        $submitter = User::factory()->create();
        $reviewer = User::factory()->create();
        $connection = ProviderConnection::factory()->forOrganisation($org)->forProvider('tiktok')
            ->create(['external_actor_id' => 'unique-'.$org->id, 'status' => 'CONNECTED']);
        ProviderConnectionCredential::factory()->forConnection($connection)->create();
        $account = SocialAccount::factory()->forBrandAndConnection($brand, $connection)->create(['status' => 'ACTIVE']);
        app(ProviderGrantOwnershipRepositoryContract::class)->linkGrant($connection, $connection->external_actor_id);
        $repo = app(ProviderGrantRevocationRepositoryContract::class);
        $request = $repo->prepare($org, $brand, $account->uuid, $submitter->id, 'Reconcile an external grant revocation.');
        $request->forceFill(['status' => $state])->save();

        return [$org, $brand, $connection, $account, $request, $submitter, $reviewer];
    }

    private function evidence(): array
    {
        return ['source_type' => 'PROVIDER_SUPPORT', 'reference' => 'CASE-00045288',
            'summary' => 'Provider support confirmed the external authorization state and application identifier.'];
    }

    public function test_two_person_manual_attestation_erases_credentials_without_faking_api_ack(): void
    {
        [$org,,$connection,$account,$request,$first,$second] = $this->case();
        $repo = app(ProviderGrantReconciliationRepositoryContract::class);
        $evidence = $repo->submit($org, $request->uuid, $first->id, $this->evidence());
        $decision = $repo->decide($org, $request->uuid, $second->id, [
            'evidence_uuid' => $evidence->uuid, 'outcome' => 'ATTESTED_REVOKED',
            'rationale' => 'A different reviewer verified the provider response and confirmed the correct OAuth grant.',
        ]);
        $this->assertSame('ATTESTED_REVOKED', $decision->outcome);
        $this->assertSame('MANUAL_ATTESTED_REVOKED', $request->fresh()->status);
        $this->assertNull($request->fresh()->provider_acknowledged_at);
        $this->assertDatabaseMissing('provider_connection_credentials', ['provider_connection_id' => $connection->id]);
        $this->assertSame('REVOKED', $connection->fresh()->status);
        $this->assertSame('RESTRICTED', $account->fresh()->status);
        $this->assertDatabaseCount('provider_grant_reconciliation_decisions', 1);
    }

    public function test_self_review_and_duplicate_decision_are_blocked(): void
    {
        [$org,,,, $request,$first,$second] = $this->case();
        $repo = app(ProviderGrantReconciliationRepositoryContract::class);
        $e = $repo->submit($org, $request->uuid, $first->id, $this->evidence());
        try {
            $repo->decide($org, $request->uuid, $first->id, [
                'evidence_uuid' => $e->uuid, 'outcome' => 'ESCALATED',
                'rationale' => 'I should not be able to verify my own submitted provider evidence.',
            ]);
            $this->fail('Self-review was incorrectly allowed.');
        } catch (ConflictHttpException) {
            $this->assertDatabaseCount('provider_grant_reconciliation_decisions', 0);
        }
        $repo->decide($org, $request->uuid, $second->id, [
            'evidence_uuid' => $e->uuid, 'outcome' => 'CONFIRMED_ACTIVE',
            'rationale' => 'Independent provider confirmation shows the grant remains active and requires reconnect.',
        ]);
        $this->expectException(ConflictHttpException::class);
        $repo->decide($org, $request->uuid, $second->id, [
            'evidence_uuid' => $e->uuid, 'outcome' => 'ATTESTED_REVOKED',
            'rationale' => 'A duplicate resolution must never be accepted by the repository.',
        ]);
    }

    public function test_cross_tenant_and_stale_evidence_are_rejected(): void
    {
        [$org,,,, $request,$first,$second] = $this->case();
        $repo = app(ProviderGrantReconciliationRepositoryContract::class);
        $a = $repo->submit($org, $request->uuid, $first->id, $this->evidence());
        $another = Organisation::factory()->create();
        $this->expectException(ModelNotFoundException::class);
        $repo->decide($another, $request->uuid, $second->id, [
            'evidence_uuid' => $a->uuid, 'outcome' => 'ESCALATED',
            'rationale' => 'A tenant cannot reconcile another organisation provider revocation.',
        ]);
    }

    public function test_superseded_evidence_cannot_be_resolved(): void
    {
        [$org,,,, $request,$first,$second] = $this->case();
        $repo = app(ProviderGrantReconciliationRepositoryContract::class);
        $old = $repo->submit($org, $request->uuid, $first->id, $this->evidence());
        $repo->submit($org, $request->uuid, $first->id, [...$this->evidence(), 'reference' => 'CASE-00045289']);
        $this->expectException(ConflictHttpException::class);
        $repo->decide($org, $request->uuid, $second->id, [
            'evidence_uuid' => $old->uuid, 'outcome' => 'ESCALATED',
            'rationale' => 'Stale evidence must not be used after another submitted record.',
        ]);
    }

    public function test_manual_active_outcome_requires_reauth_and_erases_local_credentials(): void
    {
        [$org,,$conn,$account,$request,$first,$second] = $this->case('MANUAL_REQUIRED');
        $repo = app(ProviderGrantReconciliationRepositoryContract::class);
        $e = $repo->submit($org, $request->uuid, $first->id, $this->evidence());
        $repo->decide($org, $request->uuid, $second->id, [
            'evidence_uuid' => $e->uuid, 'outcome' => 'CONFIRMED_ACTIVE',
            'rationale' => 'The provider developer console shows an active grant that requires new authorization.',
        ]);
        $this->assertSame('MANUAL_CONFIRMED_ACTIVE', $request->fresh()->status);
        $this->assertSame('REAUTH_REQUIRED', $conn->fresh()->status);
        $this->assertSame('RESTRICTED', $account->fresh()->status);
        $this->assertDatabaseMissing('provider_connection_credentials', ['provider_connection_id' => $conn->id]);
    }

    public function test_investigation_notes_can_only_escalate_and_do_not_certify_provider_revocation(): void
    {
        [$org,,,, $request,$first,$second] = $this->case();
        $repo = app(ProviderGrantReconciliationRepositoryContract::class);
        $e = $repo->submit($org, $request->uuid, $first->id, [
            'source_type' => 'INVESTIGATION', 'reference' => 'INTERNAL-RESEARCH-CASE-001',
            'summary' => 'This is an internal hypothesis that has not yet been confirmed in provider records.',
        ]);
        $this->expectException(ConflictHttpException::class);
        $repo->decide($org, $request->uuid, $second->id, [
            'evidence_uuid' => $e->uuid, 'outcome' => 'ATTESTED_REVOKED',
            'rationale' => 'Internal investigation alone should never establish external provider revocation.',
        ]);
    }

    public function test_active_credential_rotation_lease_blocks_terminal_manual_disposition(): void
    {
        [$org,,$connection,$account,$request,$first,$second] = $this->case();
        $connection->forceFill(['refresh_lease_until' => now()->addMinutes(2)])->save();
        $repo = app(ProviderGrantReconciliationRepositoryContract::class);
        $e = $repo->submit($org, $request->uuid, $first->id, $this->evidence());
        $this->expectException(ConflictHttpException::class);
        $repo->decide($org, $request->uuid, $second->id, [
            'evidence_uuid' => $e->uuid, 'outcome' => 'CONFIRMED_ACTIVE',
            'rationale' => 'Do not complete while an earlier credential renewal operation is still in flight.',
        ]);
    }

    public function test_escalation_keeps_case_open_and_does_not_erase_credentials(): void
    {
        [$org,,$connection,$account,$request,$first,$second] = $this->case();
        $repo = app(ProviderGrantReconciliationRepositoryContract::class);
        $e = $repo->submit($org, $request->uuid, $first->id, $this->evidence());
        $review = $repo->decide($org, $request->uuid, $second->id, [
            'evidence_uuid' => $e->uuid, 'outcome' => 'ESCALATED',
            'rationale' => 'Additional external evidence is needed to distinguish a revoked grant from a live grant.',
        ]);
        $this->assertSame('ESCALATED', $review->outcome);
        $this->assertSame('MANUAL_REQUIRED', $request->fresh()->status);
        $this->assertDatabaseHas('provider_connection_credentials', ['provider_connection_id' => $connection->id]);
        $this->assertNull($request->fresh()->provider_acknowledged_at);
    }

    public function test_evidence_and_decisions_are_immutable_through_eloquent(): void
    {
        [$org,,,, $request,$first,$second] = $this->case();
        $repo = app(ProviderGrantReconciliationRepositoryContract::class);
        $e = $repo->submit($org,$request->uuid,$first->id,$this->evidence());
        $e->summary = 'Edited after submission';
        $this->expectException(\LogicException::class);
        $e->save();
    }
}
