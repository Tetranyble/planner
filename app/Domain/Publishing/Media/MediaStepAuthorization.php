<?php

namespace App\Domain\Publishing\Media;

/** Must be assembled from verified current DB state by a fenced worker, never from the browser. */
final readonly class MediaStepAuthorization
{
    public function __construct(
        public bool $deploymentReleased,
        public bool $providerApproved,
        public bool $currentApprovalMatches,
        public bool $canonicalStorageVerified,
        public bool $consentVerified,
        public bool $currentLeaseFenced,
        public bool $requestNotPreviouslySent,
    ) {}

    public function allowed(): bool
    {
        return $this->deploymentReleased && $this->providerApproved
            && $this->currentApprovalMatches && $this->canonicalStorageVerified
            && $this->consentVerified && $this->currentLeaseFenced && $this->requestNotPreviouslySent;
    }
}
