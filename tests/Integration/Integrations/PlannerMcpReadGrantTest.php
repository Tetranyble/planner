<?php

namespace Tests\Integration\Integrations;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\PlannerIntegrationGrantRepositoryContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Run against disposable MySQL; composer.lock must be resolved on user's runtime. */
final class PlannerMcpReadGrantTest extends TestCase
{
    use RefreshDatabase;

    public function test_mcp_scope_is_derived_only_from_the_live_token_grant(): void
    {
        $org = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();
        $other = Brand::factory()->forOrganisation($org)->create();
        $actor = User::factory()->create();
        $repo = app(PlannerIntegrationGrantRepositoryContract::class);
        $grant = $repo->issue($org, $brand, $actor, 'MCP auditor', 7)['grant'];
        $scope = $repo->scopeForToken($grant->sanctum_token_id, $actor);
        self::assertSame($org->id, $scope['organisation']->id);
        self::assertSame($brand->id, $scope['brand']->id);
        self::assertNotSame($other->id, $scope['brand']->id);
        self::assertNull($repo->scopeForToken($grant->sanctum_token_id, User::factory()->create()));
        $repo->revoke($org, $brand, $actor, $grant->id);
        self::assertNull($repo->scopeForToken($grant->sanctum_token_id, $actor));
    }
}
