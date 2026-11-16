<?php

namespace Tests\Integration\Integrations;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\PlannerIntegrationGrant;
use App\Models\User;
use App\Repository\Contracts\PlannerIntegrationGrantRepositoryContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/** Execute against disposable MySQL before enabling any third-party API read. */
final class PlannerIntegrationGrantPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_issue_is_brand_and_user_scoped_expires_and_revokes_without_losing_audit(): void
    {
        $org = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();
        $other = Brand::factory()->forOrganisation($org)->create();
        $user = User::factory()->create();
        $repo = app(PlannerIntegrationGrantRepositoryContract::class);
        $created = $repo->issue($org, $brand, $user, 'Auditor reporting', 7);
        $grant = $created['grant'];
        $this->assertNotEmpty($created['plain_text_token']);
        $this->assertSame(['planner:read'], PersonalAccessToken::findOrFail($grant->sanctum_token_id)->abilities);
        $this->assertTrue($repo->allows($grant->sanctum_token_id, $org, $brand, $user));
        $this->assertFalse($repo->allows($grant->sanctum_token_id, $org, $other, $user));
        $this->assertFalse($repo->allows($grant->sanctum_token_id, $org, $brand, User::factory()->create()));
        $this->assertFalse($repo->revoke($org, $other, $user, $grant->id));
        $this->assertTrue($repo->revoke($org, $brand, $user, $grant->id));
        $this->assertFalse($repo->allows($grant->sanctum_token_id, $org, $brand, $user));
        $this->assertNull(PersonalAccessToken::find($grant->sanctum_token_id));
        $this->assertNotNull(PlannerIntegrationGrant::findOrFail($grant->id)->revoked_at);
    }

    public function test_expired_grant_is_not_accepted_even_when_token_exists(): void
    {
        $org = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();
        $user = User::factory()->create();
        $repo = app(PlannerIntegrationGrantRepositoryContract::class);
        $row = $repo->issue($org, $brand, $user, 'Analytics client', 1)['grant'];
        $row->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->assertFalse($repo->allows($row->sanctum_token_id, $org, $brand, $user));
        $this->assertSame(1, $repo->pruneExpired(10));
        $this->assertNull(PersonalAccessToken::find($row->sanctum_token_id));
        $this->assertNotNull($row->fresh()->revoked_at);
    }
}
