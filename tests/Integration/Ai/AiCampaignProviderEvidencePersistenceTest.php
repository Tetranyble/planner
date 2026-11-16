<?php

namespace Tests\Integration\Ai;

use App\Domain\Ai\ProviderEvidenceContext;
use App\Models\AiCampaignProposal;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Additive MySQL JSON/canonical hash fixture. To run on disposable MySQL 8. */
final class AiCampaignProviderEvidencePersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot_survives_mysql_json_storage_and_remains_independently_verifiable(): void
    {
        $org = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();
        $user = User::factory()->create();
        $signal = ['source' => 'PROVIDER_API_OBSERVED', 'provider' => 'x', 'status' => 'INCREASED',
            'sampled_pairs' => 3, 'views' => ['baseline' => 40, 'latest' => 50],
            'change_percent' => 25.0];
        $evidence = ProviderEvidenceContext::fromSignals(['x' => $signal], ['x']);
        self::assertTrue($evidence['eligible']);
        $row = AiCampaignProposal::factory()->create([
            'organisation_id' => $org->id, 'brand_id' => $brand->id, 'requested_by_user_id' => $user->id,
            'provider_evidence_hash' => $evidence['sha256'],
            'provider_evidence_snapshot' => $evidence['snapshot'],
        ]);
        $loaded = AiCampaignProposal::query()->where('organisation_id', $org->id)
            ->where('brand_id', $brand->id)->whereKey($row->id)->firstOrFail();
        self::assertSame($evidence['sha256'], ProviderEvidenceContext::digest($loaded->provider_evidence_snapshot));
        $other = Brand::factory()->forOrganisation($org)->create();
        self::assertSame(0, AiCampaignProposal::query()->where('organisation_id', $org->id)
            ->where('brand_id', $other->id)->whereKey($row->id)->count());
    }
}
