<?php

namespace App\Domain\Membership;

use DomainException;

/** Brand designations can narrow, but never expand, organisation-level Kinship grants. */
final class BrandRoleHierarchy
{
    private const RANKS = ['viewer' => 1, 'contributor' => 2, 'manager' => 3, 'owner' => 4];

    public static function assertAssignable(string $organisationRole, string $brandRole): void
    {
        if (! isset(self::RANKS[$organisationRole], self::RANKS[$brandRole])
            || self::RANKS[$brandRole] > self::RANKS[$organisationRole]) {
            throw new DomainException('Brand role cannot exceed the member\'s organisation role.');
        }
    }

    public static function isAdmin(string $role): bool
    {
        return in_array($role, ['owner', 'manager'], true);
    }
}
