<?php

namespace App\Repository\Eloquent;

use App\Models\ProviderConnection;
use App\Models\ProviderGrantGroup;
use App\Models\ProviderGrantLink;
use App\Models\ProviderGrantRevocationRequest;
use App\Repository\Contracts\ProviderGrantOwnershipRepositoryContract;
use Illuminate\Support\Facades\DB;
use LogicException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** Tracks the grant identity separately from the Pages/channels using it. */
final class ProviderGrantOwnershipRepository implements ProviderGrantOwnershipRepositoryContract
{
    private function actorDigest(string $actorId): string
    {
        $key = (string) config('social_integrations.meta_actor_hmac_key');
        if ($key === '') {
            throw new LogicException('A stable META_ACTOR_HMAC_KEY is required.');
        }

        return hash_hmac('sha256', $actorId, $key);
    }

    private function appId(): string
    {
        $id = (string) config('social_integrations.providers.facebook.client_id');
        if ($id === '') {
            throw new LogicException('META_APP_ID is required for grant ownership mapping.');
        }

        return $id;
    }

    public function linkMeta(ProviderConnection $connection, string $appScopedActorId): void
    {
        if (! in_array($connection->provider, ['facebook', 'instagram'], true)) {
            return;
        }
        $this->linkGrant($connection, $appScopedActorId);
    }

    public function linkGrant(ProviderConnection $connection, string $actorId): void
    {
        if ($actorId === '' || str_starts_with($actorId, 'channel:')) {
            return;
        }
        $meta = in_array($connection->provider, ['facebook', 'instagram'], true);
        $family = $meta ? 'meta' : $connection->provider;
        $application = $meta ? $this->appId() : (string) config('social_integrations.providers.'.$connection->provider.'.client_id');
        if ($application === '') {
            throw new LogicException('Provider client identity is required to link a grant.');
        }
        $digest = $meta ? $this->actorDigest($actorId) : hash_hmac('sha256', $actorId,
            (string) config('social_integrations.grant_actor_hmac_key'));
        if ($digest === '' || (! $meta && ! filled(config('social_integrations.grant_actor_hmac_key')))) {
            throw new LogicException('A stable grant actor HMAC key is required.');
        }
        // Lock the grant group for consistency with the revocation confirmation/worker.
        $group = ProviderGrantGroup::query()->firstOrCreate([
            'provider_family' => $family, 'application_id' => $application, 'actor_digest' => $digest,
        ]);
        $group = ProviderGrantGroup::query()->whereKey($group->id)->lockForUpdate()->firstOrFail();
        if (ProviderGrantRevocationRequest::query()->where('grant_group_id', $group->id)
            ->whereIn('status', ['READY', 'IN_FLIGHT', 'UNCERTAIN'])->exists()) {
            throw new ConflictHttpException('A grant revocation is active; reconnect after it is resolved.');
        }
        $existing = ProviderGrantLink::query()->where('provider_connection_id', $connection->id)
            ->lockForUpdate()->first();
        if ($existing && (int) $existing->grant_group_id !== (int) $group->id) {
            // Grant actor replacement requires explicit reconciliation.
            throw new LogicException('Provider connection is associated with a different grant identity.');
        }
        ($existing ?? new ProviderGrantLink)->forceFill([
            'grant_group_id' => $group->id,
            'organisation_id' => $connection->organisation_id,
            'provider_connection_id' => $connection->id,
            'detached_at' => null,
        ])->save();
    }

    public function markDetached(ProviderConnection $connection): void
    {
        ProviderGrantLink::query()->where('organisation_id', $connection->organisation_id)
            ->where('provider_connection_id', $connection->id)
            ->whereNull('detached_at')->update(['detached_at' => now(), 'updated_at' => now()]);
    }

    public function metaConnectionIds(string $appScopedActorId): array
    {
        // Deletion must continue for legacy grants even before the Meta app ID
        // has been configured; direct actor matching remains available.
        if ((string) config('social_integrations.providers.facebook.client_id') === '') {
            return [];
        }

        return DB::table('provider_grant_links as links')
            ->join('provider_grant_groups as groups', 'groups.id', '=', 'links.grant_group_id')
            ->where('groups.provider_family', 'meta')
            ->where('groups.application_id', $this->appId())
            ->where('groups.actor_digest', $this->actorDigest($appScopedActorId))
            ->pluck('links.provider_connection_id')->map(fn ($id) => (int) $id)->all();
    }
}
