<?php

namespace Tests\Integration\OAuth;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\PlannerOAuthUser;
use App\Models\User;
use App\Repository\Contracts\PlannerAgentRepositoryContract;
use App\Repository\Contracts\PlannerOAuthLinkRepositoryContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

final class PlannerOAuthBrandLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_oauth_link_is_bound_to_owner_and_existing_agent_delegation(): void
    {
        $org = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();
        $other = Brand::factory()->forOrganisation($org)->create();
        $owner = User::factory()->create();
        $agents = app(PlannerAgentRepositoryContract::class);
        $delegation = $agents->issue($org, $brand, $owner, [
            'name' => 'Review agent', 'mode' => 'REVIEW_REQUIRED',
            'account_uuids' => [], 'daily_action_limit' => 10,
            'daily_arm_limit' => 0, 'expires_in_days' => 1,
        ])['grant'];
        $links = app(PlannerOAuthLinkRepositoryContract::class);
        $first = $links->bind($owner, 'public-client-123', $delegation);
        $again = $links->bind($owner, 'public-client-123', $delegation);
        self::assertSame($first->id, $again->id);
        $principal = PlannerOAuthUser::query()->findOrFail($owner->id);
        self::assertEquals($brand->id, $links->scopeForPassportToken($principal, 'public-client-123')['brand']->id);
        self::assertNull($links->scopeForPassportToken($principal, 'other-client'));
        self::assertNull($links->scopeForPassportToken(PlannerOAuthUser::query()->findOrFail(User::factory()->create()->id), 'public-client-123'));
        self::assertNotEquals($other->id, $first->brand_id);
    }

    public function test_one_client_cannot_switch_delegations_without_new_registration(): void
    {
        $org = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();
        $owner = User::factory()->create();
        $agents = app(PlannerAgentRepositoryContract::class);
        $input = ['name' => 'First', 'mode' => 'REVIEW_REQUIRED', 'account_uuids' => [],
            'daily_action_limit' => 10, 'daily_arm_limit' => 0, 'expires_in_days' => 1];
        $one = $agents->issue($org, $brand, $owner, $input)['grant'];
        $two = $agents->issue($org, $brand, $owner, [...$input, 'name' => 'Second'])['grant'];
        $links = app(PlannerOAuthLinkRepositoryContract::class);
        $links->bind($owner, 'client-1', $one);
        $this->expectException(ConflictHttpException::class);
        $links->bind($owner, 'client-1', $two);
    }
}
