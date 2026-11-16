<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/** Route contracts are checked without seeding data or bypassing Kinship. */
final class ControllerActionRoutingTest extends TestCase
{
    public function test_non_crud_business_operations_use_single_action_controllers_and_resource_methods_are_correct(): void
    {
        $expected = [
            'api.v1.organisations.members.invite' => 'OrganisationInvitationController@store',
            'api.v1.organisations.members.change-role' => 'OrganisationMembershipController@update',
            'api.v1.organisations.members.revoke' => 'OrganisationMembershipController@destroy',
            'api.v1.organisations.members.cancel-invitation' => 'OrganisationInvitationController@destroy',
            'api.v1.organisations.brands.members.add' => 'BrandMembershipController@store',
            'api.v1.organisations.brands.members.change-role' => 'BrandMembershipController@update',
            'api.v1.organisations.brands.members.revoke' => 'BrandMembershipController@destroy',
            'organisations.members.invite' => 'OrganisationInvitationManagementController@store',
            'organisations.members.change-role' => 'OrganisationMembersController@update',
            'organisations.members.revoke' => 'OrganisationMembersController@destroy',
            'organisations.members.cancel-invitation' => 'OrganisationInvitationManagementController@destroy',
            'organisations.brands.members.add' => 'BrandMembersController@store',
            'organisations.brands.members.change-role' => 'BrandMembersController@update',
            'organisations.brands.members.revoke' => 'BrandMembersController@destroy',
            'organisation.invitations.accept' => 'AcceptOrganisationInvitationController',
            'organisations.create' => 'ShowOrganisationCreationFormController',
            'organisations.brands.create' => 'ShowBrandCreationFormController',
        ];

        $routes = app('router')->getRoutes();
        foreach ($expected as $name => $action) {
            $route = $routes->getByName($name);
            self::assertNotNull($route, "Missing {$name}");
            self::assertStringEndsWith($action, $route->getActionName(), "Unexpected action on {$name}");
        }
    }

    public function test_normalized_strategy_routes_target_one_resource_each(): void
    {
        $expected = [
            'show' => ['BrandStrategyController', 'show'],
            'profile' => ['BrandProfileController', 'update'],
            'audience' => ['BrandAudienceSegmentController', 'store'],
            'voice' => ['BrandVoiceProfileController', 'store'],
            'claim-policy' => ['BrandClaimPolicyController', 'update'],
            'claim' => ['BrandClaimController', 'store'],
            'approval-policy' => ['BrandApprovalPolicyController', 'store'],
        ];
        foreach (['api.v1.organisations.brands.strategy.', 'organisations.brands.strategy.'] as $prefix) {
            foreach ($expected as $suffix => [$controller, $method]) {
                $route = app('router')->getRoutes()->getByName($prefix.$suffix);
                self::assertNotNull($route, "Missing {$prefix}{$suffix}");
                self::assertStringEndsWith("{$controller}@{$method}", $route->getActionName());
                self::assertContains('organisation.context', $route->gatherMiddleware());
                self::assertContains('kinship.permission:'.match ($suffix) {
                    'show' => 'brand_profile.index',
                    'profile' => 'brand_profile.update',
                    'audience' => 'brand_audience_segment.store',
                    'voice' => 'brand_voice_profile.store',
                    'claim-policy' => 'brand_claim_policy.update',
                    'claim' => 'brand_claim.store',
                    'approval-policy' => 'brand_approval_policy.store',
                }, $route->gatherMiddleware());
            }
        }
    }
}
