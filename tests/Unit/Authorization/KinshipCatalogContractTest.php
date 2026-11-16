<?php

namespace Tests\Unit\Authorization;

use Illuminate\Support\Str;
use Tests\TestCase;
use Tetranyble\Kinship\Catalog\ConfigPermissionCatalog;

/** Kinship is the only permission-catalog authority, including for route edges. */
final class KinshipCatalogContractTest extends TestCase
{
    public function test_model_actions_are_discovered_using_the_configured_separator(): void
    {
        $this->assertTrue(config('kinship.catalog.enabled'));
        $this->assertTrue(config('kinship.catalog.discovery.enabled'));
        $this->assertSame('.', config('kinship.catalog.separator'));

        $names = $this->names();
        foreach ([
            'organisation.index', 'organisation.view',
            'organisation_membership.index', 'organisation_membership.update', 'organisation_membership.delete',
            'organisation_invitation.store', 'organisation_invitation.delete',
            'brand.index', 'brand.store',
            'brand_membership.index', 'brand_membership.store',
            'brand_membership.update', 'brand_membership.delete',
            'campaign.store', 'content_item.update',
            'role.index', 'permission.view', 'group.update',
        ] as $expected) {
            $this->assertContains($expected, $names);
        }

        $this->assertNotContains('brand_membership_update', $names);
    }

    public function test_only_non_crud_capabilities_are_defined_as_special_permissions(): void
    {
        $names = $this->names();
        foreach ([
            'content.approve', 'publication.schedule', 'publication.cancel',
            'publication.retry', 'social-account.connect', 'social-account.disconnect',
            'integration.manage', 'automation.execute', 'ai-run.execute',
            'ai-run.approve-action', 'lead.export', 'analytics.export',
        ] as $expected) {
            $this->assertContains($expected, $names);
        }

        $this->assertArrayNotHasKey('brand_membership.store', config('kinship.catalog.permissions'));
    }

    public function test_every_role_pattern_matches_a_permission_and_grants_are_least_privilege(): void
    {
        $permissions = $this->names();
        $roles = app(ConfigPermissionCatalog::class)->roles();
        $roleMap = [];
        foreach ($roles as $role) {
            $roleMap[$role->name] = $role->permissions;
            foreach ($role->permissions as $pattern) {
                $this->assertTrue(
                    collect($permissions)->contains(fn (string $permission): bool => $pattern === '*' || Str::is($pattern, $permission)),
                    "Invalid Kinship permission pattern [{$role->name}:{$pattern}]",
                );
            }
        }
        $this->assertSame(['viewer', 'contributor', 'manager', 'owner'], array_keys($roleMap));
        $this->assertSame(['*'], $roleMap['owner']);
        foreach (['viewer', 'contributor', 'manager'] as $role) {
            $this->assertNotContains('*', $roleMap[$role]);
            $this->assertNotContains('organisation_membership.update', $roleMap[$role]);
            $this->assertNotContains('integration.manage', $roleMap[$role]);
            $this->assertNotContains('provider_connection.view', $roleMap[$role]);
        }
        $this->assertNotContains('content.approve', $roleMap['contributor']);
        $this->assertNotContains('publication.schedule', $roleMap['contributor']);
        $this->assertContains('brand_membership.store', $roleMap['manager']);
        $this->assertContains('content.approve', $roleMap['manager']);
    }

    public function test_membership_routes_enforce_their_action_at_the_http_edge(): void
    {
        $routes = app('router')->getRoutes();
        $expectations = [
            'organisations.members.index' => ['kinship.role:owner', 'kinship.permission:organisation_membership.index'],
            'organisations.members.invite' => ['kinship.role:owner', 'kinship.permission:organisation_invitation.store'],
            'organisations.members.change-role' => ['kinship.role:owner', 'kinship.permission:organisation_membership.update'],
            'organisations.members.revoke' => ['kinship.role:owner', 'kinship.permission:organisation_membership.delete'],
            'organisations.members.cancel-invitation' => ['kinship.role:owner', 'kinship.permission:organisation_invitation.delete'],
            'organisations.brands.members.index' => ['kinship.role:owner,manager', 'kinship.permission:brand_membership.index'],
            'organisations.brands.members.add' => ['kinship.role:owner,manager', 'kinship.permission:brand_membership.store'],
            'organisations.brands.members.change-role' => ['kinship.role:owner,manager', 'kinship.permission:brand_membership.update'],
            'organisations.brands.members.revoke' => ['kinship.role:owner,manager', 'kinship.permission:brand_membership.delete'],
        ];
        foreach ($expectations as $name => $middleware) {
            $route = $routes->getByName($name);
            $this->assertNotNull($route, "Missing route: {$name}");
            $applied = $route->gatherMiddleware();
            $this->assertContains('organisation.context', $applied, "Missing workspace context: {$name}");
            foreach ($middleware as $item) {
                $this->assertContains($item, $applied, "Missing middleware {$item} on {$name}");
            }
        }
    }

    /** @return list<string> */
    private function names(): array
    {
        return array_map(
            fn ($definition): string => $definition->name,
            app(ConfigPermissionCatalog::class)->permissions(),
        );
    }
}
