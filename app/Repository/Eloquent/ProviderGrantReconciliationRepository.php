<?php

namespace App\Repository\Eloquent;

use App\Enums\Connection\ProviderConnectionStatus;
use App\Enums\Connection\SocialAccountStatus;
use App\Models\Organisation;
use App\Models\ProviderConnection;
use App\Models\ProviderConnectionCredential;
use App\Models\ProviderGrantGroup;
use App\Models\ProviderGrantLink;
use App\Models\ProviderGrantReconciliationDecision;
use App\Models\ProviderGrantReconciliationEvidence;
use App\Models\ProviderGrantRevocationRequest;
use App\Models\SocialAccount;
use App\Models\SocialAccountCapability;
use App\Models\SocialAccountStatusEvent;
use App\Repository\Contracts\ProviderGrantReconciliationRepositoryContract;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** All persistence and row-locking stays behind the repository boundary. */
final class ProviderGrantReconciliationRepository implements ProviderGrantReconciliationRepositoryContract
{
    private const PENDING = ['UNCERTAIN', 'MANUAL_REQUIRED'];

    private const OUTCOMES = ['ATTESTED_REVOKED', 'CONFIRMED_ACTIVE', 'ESCALATED'];

    public function directory(Organisation $organisation, int $actorId): array
    {
        $requests = ProviderGrantRevocationRequest::query()->where('organisation_id', $organisation->id)
            ->whereIn('status', self::PENDING)->orderByDesc('id')->limit(100)->get();

        return $requests->map(function (ProviderGrantRevocationRequest $request) use ($organisation, $actorId): array {
            $latest = ProviderGrantReconciliationEvidence::query()->where('organisation_id', $organisation->id)
                ->where('revocation_request_id', $request->id)->orderByDesc('id')->first();

            return [
                'uuid' => $request->uuid,
                'provider' => $request->provider,
                'status' => $request->status,
                'safe_error_code' => $request->safe_error_code,
                'affected_connections' => (int) $request->affected_connections,
                'reason' => $request->reason,
                'latest_evidence' => $latest ? [
                    'uuid' => $latest->uuid, 'source_type' => $latest->source_type,
                    'reference' => $latest->reference, 'summary' => $latest->summary,
                    'submitted_at' => $latest->created_at?->toIso8601String(),
                ] : null,
                'can_decide' => $latest !== null && (int) $latest->submitted_by_user_id !== $actorId,
            ];
        })->all();
    }

    public function submit(Organisation $organisation, string $requestUuid, int $actorId, array $data): ProviderGrantReconciliationEvidence
    {
        return DB::transaction(function () use ($organisation, $requestUuid, $actorId, $data): ProviderGrantReconciliationEvidence {
            $request = $this->lockPending($organisation, $requestUuid);
            $reference = trim($data['reference']);
            $summary = trim($data['summary']);
            $digest = hash('sha256', json_encode([
                'request' => $request->uuid, 'source_type' => $data['source_type'],
                'reference' => $reference, 'summary' => $summary,
            ], JSON_THROW_ON_ERROR));

            return ProviderGrantReconciliationEvidence::query()->create([
                'organisation_id' => $organisation->id, 'revocation_request_id' => $request->id,
                'submitted_by_user_id' => $actorId, 'source_type' => $data['source_type'],
                'reference' => $reference, 'summary' => $summary,
                'evidence_digest' => $digest, 'created_at' => now(),
            ]);
        }, attempts: 3);
    }

    public function decide(Organisation $organisation, string $requestUuid, int $actorId, array $data): ProviderGrantReconciliationDecision
    {
        return DB::transaction(function () use ($organisation, $requestUuid, $actorId, $data): ProviderGrantReconciliationDecision {
            $request = $this->lockPending($organisation, $requestUuid);
            $evidence = ProviderGrantReconciliationEvidence::query()->where('organisation_id', $organisation->id)
                ->where('revocation_request_id', $request->id)->where('uuid', $data['evidence_uuid'])
                ->firstOrFail();
            $latest = ProviderGrantReconciliationEvidence::query()->where('organisation_id', $organisation->id)
                ->where('revocation_request_id', $request->id)->orderByDesc('id')->firstOrFail();
            if ((int) $evidence->id !== (int) $latest->id) {
                throw new ConflictHttpException('Evidence was superseded. Review the latest submission.');
            }
            if ((int) $evidence->submitted_by_user_id === $actorId) {
                throw new ConflictHttpException('A different organisation owner must verify the evidence.');
            }
            if (ProviderGrantReconciliationDecision::query()->where('evidence_id', $evidence->id)->exists()) {
                throw new ConflictHttpException('This evidence has already been reviewed. Submit new evidence for further review.');
            }
            if ($data['outcome'] !== 'ESCALATED' && $evidence->source_type === 'INVESTIGATION') {
                throw new ConflictHttpException('An investigation note is not independent provider evidence. Escalate or supply provider confirmation.');
            }
            if (! in_array($data['outcome'], self::OUTCOMES, true)) {
                throw new ConflictHttpException('Unsupported reconciliation decision.');
            }
            if ($data['outcome'] !== 'ESCALATED') {
                $this->assertImpactUnchanged($organisation, $request);
            }
            $decision = ProviderGrantReconciliationDecision::query()->create([
                'organisation_id' => $organisation->id, 'revocation_request_id' => $request->id,
                'evidence_id' => $evidence->id, 'reviewed_by_user_id' => $actorId,
                'outcome' => $data['outcome'], 'rationale' => trim($data['rationale']),
                'evidence_digest' => $evidence->evidence_digest, 'created_at' => now(),
            ]);
            if ($data['outcome'] === 'ESCALATED') {
                $request->forceFill(['status' => 'MANUAL_REQUIRED', 'safe_error_code' => 'RECONCILIATION_ESCALATED'])->save();

                return $decision;
            }

            // A human decision is not provider acknowledgement. We deliberately
            // never write provider_acknowledged_at here.
            $this->quarantineConnections($organisation, $request, $data['outcome']);
            $request->forceFill([
                'status' => $data['outcome'] === 'ATTESTED_REVOKED' ? 'MANUAL_ATTESTED_REVOKED' : 'MANUAL_CONFIRMED_ACTIVE',
                'safe_error_code' => $data['outcome'] === 'ATTESTED_REVOKED'
                    ? 'HUMAN_ATTESTATION_NOT_PROVIDER_ACK' : 'REAUTHORISATION_REQUIRED',
                'lease_token' => null, 'lease_until' => null,
            ])->save();

            return $decision;
        }, attempts: 3);
    }

    private function lockPending(Organisation $organisation, string $requestUuid): ProviderGrantRevocationRequest
    {
        $request = ProviderGrantRevocationRequest::query()->where('organisation_id', $organisation->id)
            ->where('uuid', $requestUuid)->lockForUpdate()->firstOrFail();
        if (! in_array($request->status, self::PENDING, true)) {
            throw new ConflictHttpException('Only uncertain or manual-required grants can be reconciled.');
        }

        return $request;
    }

    private function attachedLinks(int $groupId): Collection
    {
        return ProviderGrantLink::query()->where('grant_group_id', $groupId)
            ->whereNull('detached_at')->orderBy('provider_connection_id')->get();
    }

    private function assertImpactUnchanged(Organisation $organisation, ProviderGrantRevocationRequest $request): void
    {
        $group = ProviderGrantGroup::query()->whereKey($request->grant_group_id)->lockForUpdate()->firstOrFail();
        $links = $this->attachedLinks((int) $group->id);
        if ($links->isEmpty() || $links->count() !== (int) $request->affected_connections ||
            $links->contains(fn ($l) => (int) $l->organisation_id !== (int) $organisation->id)) {
            throw new ConflictHttpException('Grant ownership requires investigation before manual disposition.');
        }
        $digest = hash('sha256', $links->map(fn ($l) => $l->organisation_id.':'.$l->provider_connection_id)->implode('|'));
        if (! hash_equals($request->impact_digest, $digest)) {
            throw new ConflictHttpException('Grant links changed since owner confirmation.');
        }
        // Manual closure must not race credential rotation or a provider inspection.
        $busy = ProviderConnection::query()->where('organisation_id', $organisation->id)
            ->whereIn('id', $links->pluck('provider_connection_id')->all())
            ->where(function ($query): void {
                $query->where('refresh_lease_until', '>', now())
                    ->orWhere('inspection_lease_until', '>', now());
            })->exists();
        if ($busy) {
            throw new ConflictHttpException('A provider credential operation is still in flight. Reconcile after its lease is cleared.');
        }
        $origin = ProviderConnection::query()->where('organisation_id', $organisation->id)
            ->whereKey($request->provider_connection_id)->firstOrFail();
        $family = in_array($origin->provider, ['facebook', 'instagram'], true) ? ['facebook', 'instagram'] : [$origin->provider];
        if (ProviderConnection::query()->whereIn('provider', $family)
            ->where('external_actor_id', $origin->external_actor_id)
            ->whereIn('status', ['CONNECTED', 'REAUTH_REQUIRED', 'RESTRICTED'])
            ->whereNotIn('id', $links->pluck('provider_connection_id')->all())->exists()) {
            throw new ConflictHttpException('Unmapped legacy connections require reconciliation before disposition.');
        }
    }

    private function quarantineConnections(Organisation $organisation, ProviderGrantRevocationRequest $request, string $outcome): void
    {
        $links = $this->attachedLinks((int) $request->grant_group_id);
        foreach ($links as $link) {
            $connection = ProviderConnection::query()->where('organisation_id', $organisation->id)
                ->whereKey($link->provider_connection_id)->lockForUpdate()->firstOrFail();
            ProviderConnectionCredential::query()->where('organisation_id', $organisation->id)
                ->where('provider_connection_id', $connection->id)->delete();
            $connection->forceFill([
                'status' => $outcome === 'ATTESTED_REVOKED' ? ProviderConnectionStatus::REVOKED->value : ProviderConnectionStatus::REAUTH_REQUIRED->value,
                'access_token_secret_ref' => 'encrypted-db:erased', 'refresh_token_secret_ref' => null,
                'refresh_lease_token' => null, 'refresh_lease_until' => null,
                'inspection_lease_token' => null, 'inspection_lease_until' => null,
                'reconnect_reason' => $outcome === 'ATTESTED_REVOKED'
                    ? 'MANUALLY_ATTESTED_PROVIDER_REVOCATION' : 'PROVIDER_GRANT_STILL_ACTIVE_REAUTHORISATION_REQUIRED',
            ])->save();
            $accounts = SocialAccount::query()->where('organisation_id', $organisation->id)
                ->where('provider_connection_id', $connection->id)->where('status', SocialAccountStatus::ACTIVE->value)->get();
            foreach ($accounts as $account) {
                $account->forceFill(['status' => SocialAccountStatus::RESTRICTED->value])->save();
                SocialAccountStatusEvent::query()->create([
                    'organisation_id' => $account->organisation_id, 'brand_id' => $account->brand_id,
                    'social_account_id' => $account->id, 'event_type' => 'MANUAL_GRANT_RECONCILIATION',
                    'old_status' => SocialAccountStatus::ACTIVE->value,
                    'new_status' => SocialAccountStatus::RESTRICTED->value, 'recorded_at' => now(),
                ]);
            }
            SocialAccountCapability::query()->where('organisation_id', $organisation->id)
                ->whereIn('social_account_id', SocialAccount::query()->where('organisation_id', $organisation->id)
                    ->where('provider_connection_id', $connection->id)->select('id'))
                ->update(['mode' => 'UNAVAILABLE', 'reason' => 'Manual grant reconciliation; access restricted', 'checked_at' => now()]);
            if ($outcome === 'ATTESTED_REVOKED') {
                $link->forceFill(['detached_at' => now()])->save();
            }
        }
    }
}
