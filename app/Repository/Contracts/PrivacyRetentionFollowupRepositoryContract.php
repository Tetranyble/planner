<?php

namespace App\Repository\Contracts;

use App\Models\Organisation;
use App\Models\User;

interface PrivacyRetentionFollowupRepositoryContract
{
    /** Idempotently queue legal-retention decisions once their review date is reached. */
    public function enqueueDue(int $limit): int;

    /** @return list<array<string,mixed>> */
    public function directory(Organisation $organisation): array;

    public function acknowledge(Organisation $organisation, User $actor, int $id, string $notes): void;
}
