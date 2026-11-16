<?php

namespace Tests\Integration\BrandStrategy;

use App\Models\Brand;
use App\Models\BrandApprovalPolicy;
use App\Models\BrandApprovalRule;
use App\Models\BrandAudienceGoal;
use App\Models\BrandAudiencePainPoint;
use App\Models\BrandAudienceRole;
use App\Models\BrandAudienceSegment;
use App\Models\BrandClaim;
use App\Models\BrandClaimPolicy;
use App\Models\BrandProfile;
use App\Models\BrandVoiceProfile;
use App\Models\BrandVoiceRule;
use App\Models\BrandVoiceTerm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BrandStrategyFactoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_core_strategy_factories_create_relationally_consistent_branded_graph(): void
    {
        $brand = Brand::factory()->create();
        $profile = BrandProfile::factory()->forBrand($brand)->create();
        $segment = BrandAudienceSegment::factory()->forBrand($brand)->create();
        $role = BrandAudienceRole::factory()->forBrandAudienceSegment($segment)->create();
        $pain = BrandAudiencePainPoint::factory()->forBrandAudienceSegment($segment)->create();
        $goal = BrandAudienceGoal::factory()->forBrandAudienceSegment($segment)->create();
        $voice = BrandVoiceProfile::factory()->forBrand($brand)->create();
        $rule = BrandVoiceRule::factory()->forBrandVoiceProfile($voice)->create();
        $term = BrandVoiceTerm::factory()->forBrandVoiceProfile($voice)->create();
        $policy = BrandClaimPolicy::factory()->forBrand($brand)->create();
        $claim = BrandClaim::factory()->forBrand($brand)->create();
        $approval = BrandApprovalPolicy::factory()->forBrand($brand)->create();
        $approvalRule = BrandApprovalRule::factory()->forBrandApprovalPolicy($approval)->create();
        foreach ([$profile, $segment, $role, $pain, $goal, $voice, $rule, $term, $policy, $claim, $approval, $approvalRule] as $row) {
            self::assertSame((int) $brand->id, (int) $row->brand_id);
            self::assertSame((int) $brand->organisation_id, (int) $row->organisation_id);
        }
        self::assertSame((int) $segment->id, (int) $role->audience_segment_id);
        self::assertSame((int) $voice->id, (int) $term->voice_profile_id);
        self::assertSame((int) $approval->id, (int) $approvalRule->policy_id);
    }
}
