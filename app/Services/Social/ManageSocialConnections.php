<?php

namespace App\Services\Social;

use App\Authorization\BrandPermissionGuard;
use App\Authorization\CurrentOrganisation;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\AuditEventRepositoryContract;
use App\Repository\Contracts\MetaDataDeletionRepositoryContract;
use App\Repository\Contracts\SocialConnectionRepositoryContract;
use App\Repository\Contracts\SocialOAuthSelectionRepositoryContract;
use App\Social\SocialProviderRegistry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class ManageSocialConnections
{
    public function __construct(
        private readonly BrandPermissionGuard $guard,
        private readonly CurrentOrganisation $context,
        private readonly SocialConnectionRepositoryContract $repository,
        private readonly SocialOAuthSelectionRepositoryContract $selections,
        private readonly AuditEventRepositoryContract $audit,
        private readonly SocialProviderRegistry $providers,
        private readonly MetaDataDeletionRepositoryContract $privacy,
    ) {}

    private function authorize(User $actor, Organisation $organisation, Brand $brand, string $permission): void
    {
        if ((int) $brand->organisation_id !== (int) $organisation->id
            || ! $this->guard->allows($actor, $brand, $permission)) {
            throw new AuthorizationException('Social account permission denied.');
        }
    }

    public function directory(User $actor, Organisation $organisation, Brand $brand): array
    {
        $this->authorize($actor, $organisation, $brand, 'social_account.index');

        return [
            'accounts' => $this->repository->directory($organisation, $brand),
            'providers' => $this->providers->availability(),
        ];
    }

    public function start(User $actor, Organisation $organisation, Brand $brand, string $provider): string
    {
        $this->authorize($actor, $organisation, $brand, 'social-account.connect');
        $adapter = $this->providers->resolve($provider);
        $redirectUri = URL::route('social.oauth.callback', ['provider' => $provider]);
        if (! app()->environment(['local', 'testing']) && ! str_starts_with($redirectUri, 'https://')) {
            throw new BadRequestHttpException('OAuth callbacks require an HTTPS origin.');
        }
        $state = Str::random(64);
        $this->repository->createAttempt($organisation, $brand, $actor, $provider,
            hash('sha256', $state), $redirectUri);

        return $adapter->authorizationUrl($state, $redirectUri);
    }

    public function complete(User $actor, string $provider, string $state, ?string $code, ?string $error = null): array
    {
        if (strlen($state) !== 64 || ! ctype_alnum($state)) {
            throw new BadRequestHttpException('Invalid OAuth callback state.');
        }
        $attempt = $this->repository->consumeAttempt(hash('sha256', $state), $provider, $actor);
        $scope = $this->repository->findAttemptScope($attempt);
        $organisation = $scope['organisation'];
        $brand = $scope['brand'];

        return $this->context->within($actor, (int) $organisation->id, function () use ($actor, $organisation, $brand, $provider, $attempt, $code, $error, $state): array {
            $this->authorize($actor, $organisation, $brand, 'social-account.connect');
            if ($error !== null || $code === null || trim($code) === '') {
                throw new BadRequestHttpException('Social provider consent was denied or cancelled.');
            }
            $adapter = $this->providers->resolve($provider);
            // Provider I/O is external and is never performed within a database transaction.
            $grant = $adapter->exchange($code, $attempt->redirect_uri, $state);
            if (in_array($provider, ['facebook', 'instagram'], true) && $this->privacy->blocksExistingOAuth($grant->actorId, $attempt->created_at)) {
                throw new BadRequestHttpException('The existing Meta authorization was withdrawn. Start a new connection after privacy processing completes.');
            }
            $accounts = $adapter->discover($grant);
            if (count($accounts) > 1) {
                // Never silently claim every Facebook Page/YouTube channel for one brand.
                $pending = $this->selections->create($organisation, $brand, $actor, $provider, $grant, $accounts);

                return [
                    'organisation_uuid' => $organisation->uuid,
                    'brand_uuid' => $brand->uuid,
                    'selection_uuid' => $pending->uuid,
                    'account_uuids' => [],
                ];
            }
            $ids = DB::transaction(function () use ($actor, $organisation, $brand, $provider, $grant, $accounts, $adapter): array {
                $this->repository->lockScope($organisation, $brand);
                $this->authorize($actor, $organisation, $brand, 'social-account.connect');
                $ids = $this->repository->connect($organisation, $brand, $provider, $grant,
                    $accounts, fn ($account) => $adapter->capabilities($grant, $account));
                $this->audit->record($organisation, $actor, 'social.oauth.connected', 'social_account',
                    implode(',', $ids), $brand, ['provider' => $provider, 'account_count' => count($ids)]);

                return $ids;
            }, attempts: 3);

            return [
                'organisation_uuid' => $organisation->uuid,
                'brand_uuid' => $brand->uuid,
                'account_uuids' => $ids,
            ];
        });
    }

    /** Present only public candidate metadata. Tokens remain encrypted in the database. */
    public function selection(User $actor, Organisation $organisation, Brand $brand, string $selectionUuid): array
    {
        $this->authorize($actor, $organisation, $brand, 'social-account.connect');
        $pending = $this->selections->findForActor($organisation, $brand, $actor, $selectionUuid);

        return [
            'uuid' => $pending->uuid,
            'provider' => $pending->provider,
            'expires_at' => $pending->expires_at->toIso8601String(),
            'accounts' => array_map(fn ($account): array => [
                'id' => $account->externalId,
                'kind' => $account->kind,
                'name' => $account->displayName,
                'handle' => $account->handle,
            ], $this->selections->accounts($pending)),
        ];
    }

    /** @param list<string> $accountIds */
    public function confirmSelection(User $actor, Organisation $organisation, Brand $brand, string $selectionUuid, array $accountIds): array
    {
        $this->authorize($actor, $organisation, $brand, 'social-account.connect');
        if ($accountIds === [] || count($accountIds) > 100 || count(array_unique($accountIds)) !== count($accountIds)) {
            throw ValidationException::withMessages(['account_ids' => 'Select unique accounts to connect.']);
        }

        return $this->context->within($actor, (int) $organisation->id, function () use (
            $actor, $organisation, $brand, $selectionUuid, $accountIds,
        ): array {
            return DB::transaction(function () use ($actor, $organisation, $brand, $selectionUuid, $accountIds): array {
                $this->repository->lockScope($organisation, $brand);
                $this->authorize($actor, $organisation, $brand, 'social-account.connect');
                $pending = $this->selections->lockForConsumption($organisation, $brand, $actor, $selectionUuid);
                $candidates = $this->selections->accounts($pending);
                $available = [];
                foreach ($candidates as $candidate) {
                    $available[$candidate->externalId] = $candidate;
                }
                $chosen = [];
                foreach ($accountIds as $id) {
                    if (! isset($available[$id])) {
                        throw ValidationException::withMessages(['account_ids' => 'Unknown or unauthorized provider account selected.']);
                    }
                    $chosen[] = $available[$id];
                }
                $grant = $this->selections->grant($pending);
                if (in_array($pending->provider, ['facebook', 'instagram'], true) && $this->privacy->blocksExistingOAuth($grant->actorId, $pending->created_at)) {
                    throw new BadRequestHttpException('This Meta grant was withdrawn. Start a new connection after privacy processing completes.');
                }
                $provider = $pending->provider;
                $adapter = $this->providers->resolve($provider);
                $ids = $this->repository->connect($organisation, $brand, $provider, $grant,
                    $chosen, fn ($account) => $adapter->capabilities($grant, $account));
                $this->selections->markConsumed($pending);
                $this->audit->record($organisation, $actor, 'social.oauth.selected_accounts_connected',
                    'social_account', implode(',', $ids), $brand, ['provider' => $provider, 'account_count' => count($ids)]);

                return [
                    'organisation_uuid' => $organisation->uuid,
                    'brand_uuid' => $brand->uuid,
                    'account_uuids' => $ids,
                ];
            }, attempts: 3);
        });
    }

    public function disconnect(User $actor, Organisation $organisation, Brand $brand, string $accountUuid): void
    {
        $this->authorize($actor, $organisation, $brand, 'social-account.disconnect');
        DB::transaction(function () use ($actor, $organisation, $brand, $accountUuid): void {
            $this->repository->lockScope($organisation, $brand);
            $this->authorize($actor, $organisation, $brand, 'social-account.disconnect');
            $account = $this->repository->disconnect($organisation, $brand, $accountUuid);
            $this->audit->record($organisation, $actor, 'social.account.disconnected_locally',
                'social_account', $account->uuid, $brand, ['provider' => $account->provider]);
        }, attempts: 3);
    }
}
