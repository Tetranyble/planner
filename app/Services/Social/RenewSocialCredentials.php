<?php

namespace App\Services\Social;

use App\Repository\Contracts\ProviderTokenLifecycleRepositoryContract;
use App\Social\Contracts\RefreshableSocialProvider;
use App\Social\Exceptions\TokenRefreshFailure;
use App\Social\SocialProviderRegistry;

/** Runs from the scheduler. No user/tenant context; only repo-owned CONNECTED credentials are eligible. */
final class RenewSocialCredentials
{
    public function __construct(
        private readonly ProviderTokenLifecycleRepositoryContract $repository,
        private readonly SocialProviderRegistry $providers,
    ) {}

    /** Returns counts only. Never exposes encrypted or plaintext credentials to CLI output. */
    public function run(int $limit = 20, int $leadMinutes = 20): array
    {
        $results = ['checked' => 0, 'renewed' => 0, 'reauthorization' => 0, 'skipped' => 0, 'deferred' => 0];
        foreach ($this->repository->dueIds($limit, $leadMinutes) as $connectionId) {
            $results['checked']++;
            $lease = $this->repository->acquire($connectionId, $leadMinutes);
            if ($lease === null) {
                $results['skipped']++;

                continue;
            }
            try {
                if (! $this->providers->isConfigured($lease->provider)) {
                    throw new TokenRefreshFailure('PROVIDER_NOT_CONFIGURED', true);
                }
                $adapter = $this->providers->resolve($lease->provider);
                if (! $adapter instanceof RefreshableSocialProvider) {
                    throw new TokenRefreshFailure('REFRESH_UNSUPPORTED');
                }
                $result = $adapter->refresh($lease->refreshToken);
                if ($this->repository->complete($lease, $result)) {
                    $results['renewed']++;
                } else {
                    $results['reauthorization']++;
                }
            } catch (TokenRefreshFailure $failure) {
                $this->repository->fail($lease, $failure->safeCode, $failure->retrySafe);
                $results[$failure->retrySafe ? 'deferred' : 'reauthorization']++;
            } catch (\Throwable) {
                // Unexpected exceptions may have occurred AFTER a provider consumed a rotating
                // refresh token. Reauthorization is safer than replaying an unknown result.
                $this->repository->fail($lease, 'PROVIDER_OUTCOME_UNCERTAIN', false);
                $results['reauthorization']++;
            }
        }

        return $results;
    }
}
