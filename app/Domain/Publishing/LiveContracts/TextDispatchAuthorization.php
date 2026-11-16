<?php

namespace App\Domain\Publishing\LiveContracts;

/** Ephemeral, exact-attempt proof. Never assembled from an API request body. */
final readonly class TextDispatchAuthorization
{
    public function __construct(
        public bool $globalKillSwitchOff,
        public bool $providerApproved,
        public bool $accountAndScopeVerified,
        public bool $credentialHealthy,
        public bool $approvalAndHashCurrent,
        public bool $canonicalMediaVerified,
        public bool $uniqueAttemptPersisted,
        public bool $fencedLeaseOwned,
        public bool $dueNow,
        public bool $duplicateSafetyVerified,
    ) {}

    public function blockers(): array
    {
        $checks = [
            'GLOBAL_KILL_SWITCH' => $this->globalKillSwitchOff,
            'PROVIDER_APPROVAL' => $this->providerApproved,
            'ACCOUNT_SCOPE' => $this->accountAndScopeVerified,
            'CREDENTIAL_HEALTH' => $this->credentialHealthy,
            'APPROVAL_HASH' => $this->approvalAndHashCurrent,
            'MEDIA_INTEGRITY' => $this->canonicalMediaVerified,
            'PERSISTED_ATTEMPT' => $this->uniqueAttemptPersisted,
            'FENCED_LEASE' => $this->fencedLeaseOwned,
            'DUE_TIME' => $this->dueNow,
            'DUPLICATE_SAFETY' => $this->duplicateSafetyVerified,
        ];

        return array_keys(array_filter($checks, static fn (bool $passed): bool => ! $passed));
    }
}
