<?php

namespace Tests\Unit\Membership;

use App\Domain\Membership\BrandRoleHierarchy;
use DomainException;
use PHPUnit\Framework\TestCase;

final class BrandRoleHierarchyTest extends TestCase
{
    public function test_brand_role_cannot_exceed_organisation_role(): void
    {
        BrandRoleHierarchy::assertAssignable('owner', 'viewer');
        BrandRoleHierarchy::assertAssignable('manager', 'manager');
        BrandRoleHierarchy::assertAssignable('contributor', 'viewer');
        $this->expectException(DomainException::class);
        BrandRoleHierarchy::assertAssignable('viewer', 'manager');
    }

    public function test_unknown_roles_are_rejected(): void
    {
        $this->expectException(DomainException::class);
        BrandRoleHierarchy::assertAssignable('owner', 'superadmin');
    }

    public function test_only_owner_and_manager_designations_are_administrative(): void
    {
        self::assertTrue(BrandRoleHierarchy::isAdmin('owner'));
        self::assertTrue(BrandRoleHierarchy::isAdmin('manager'));
        self::assertFalse(BrandRoleHierarchy::isAdmin('contributor'));
        self::assertFalse(BrandRoleHierarchy::isAdmin('viewer'));
    }
}
