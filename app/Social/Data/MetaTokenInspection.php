<?php

namespace App\Social\Data;

/** Only the provider's attested data, not the original access token. */
final readonly class MetaTokenInspection
{
    /** @param list<string> $scopes @param array<string,list<string>> $targetedScopes */
    public function __construct(
        public bool $valid,
        public string $appId,
        public string $userId,
        public array $scopes,
        public array $targetedScopes,
        public ?int $expiresAt,
        public ?int $dataAccessExpiresAt,
    ) {}

    public function allows(string $permission, string $targetId): bool
    {
        if (! in_array($permission, $this->scopes, true)) {
            return false;
        }
        if (! array_key_exists($permission, $this->targetedScopes)) {
            return true;
        }

        // If the provider grants a permission only to specific targets, do not
        // extrapolate its grant to a different Page/Instagram identity.
        return in_array($targetId, $this->targetedScopes[$permission], true);
    }
}
