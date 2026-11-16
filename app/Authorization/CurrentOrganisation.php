<?php

namespace App\Authorization;

use App\Enums\StateEnum;
use App\Enums\StatusEnum;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/** Request-scoped, fail-closed identity of the organisation being operated on. */
final class CurrentOrganisation
{
    private ?int $organisationId = null;

    private ?int $userId = null;

    /** Only an active organisation member can establish a context. */
    public function select(User $user, int $organisationId): void
    {
        if (! $user->exists || ! $this->membershipIsActive($user, $organisationId)) {
            throw new AuthorizationException('An active organisation membership is required.');
        }

        $this->organisationId = $organisationId;
        $this->userId = (int) $user->getKey();
    }

    /** Return null when no verified, still-active context exists. */
    public function idFor(User $user): ?int
    {
        if ($this->organisationId === null || $this->userId !== (int) $user->getKey()) {
            return null;
        }

        return $this->membershipIsActive($user, $this->organisationId)
            ? $this->organisationId
            : null;
    }

    public function organisationFor(User $user): ?Organisation
    {
        $id = $this->idFor($user);

        return $id !== null ? Organisation::query()->find($id) : null;
    }

    /** Scoped temporary selection for trusted membership provisioning flows. */
    public function within(User $user, int $organisationId, callable $operation): mixed
    {
        $previousId = $this->organisationId;
        $previousUserId = $this->userId;
        $this->select($user, $organisationId);

        try {
            return $operation();
        } finally {
            $this->organisationId = $previousId;
            $this->userId = $previousUserId;
        }
    }

    private function membershipIsActive(User $user, int $organisationId): bool
    {
        return DB::table('organisation_memberships')
            ->join('organisations', 'organisations.id', '=', 'organisation_memberships.organisation_id')
            ->where('organisation_memberships.organisation_id', $organisationId)
            ->where('organisation_memberships.user_id', $user->getKey())
            ->where('organisation_memberships.state', StateEnum::ACTIVE->value)
            ->where('organisations.status', StatusEnum::ACTIVE->value)
            ->exists();
    }
}
