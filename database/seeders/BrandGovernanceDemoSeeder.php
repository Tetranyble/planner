<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\BrandClaim;
use App\Models\BrandClaimEvidence;
use App\Models\BrandKnowledgeSource;
use Illuminate\Database\Seeder;
use LogicException;

/**
 * Opt-in only. Generates UNSUPPORTED, untrusted sample sources and pending evidence.
 * Never creates trusted sources, approved claims or activated policies.
 */
final class BrandGovernanceDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Demo governance data cannot be seeded outside local/testing.');
        }
        $uuid = (string) env('PLANNER_DEMO_BRAND_UUID', '');
        if ($uuid === '') {
            throw new LogicException('Set PLANNER_DEMO_BRAND_UUID to an existing development brand.');
        }
        $brand = Brand::query()->where('uuid', $uuid)->firstOrFail();
        $hash = hash('sha256', implode("\n", ['MANUAL', '', 'Demonstration-only information. Not proof of a production capability.']));
        $source = BrandKnowledgeSource::query()->where('organisation_id', $brand->organisation_id)
            ->where('brand_id', $brand->id)->where('content_hash', $hash)->first();
        if ($source === null) {
            $source = BrandKnowledgeSource::factory()->forBrand($brand)->create([
                'title' => 'Unverified demo reference', 'source_type' => 'MANUAL',
                'content_text' => 'Demonstration-only information. Not proof of a production capability.',
                'content_hash' => $hash, 'trust_status' => 'PENDING',
                'object_key' => null, 'source_url' => null,
            ]);
        }
        $draftText = 'DEMO DRAFT: hypothetical feature still requiring independent verification';
        $claim = BrandClaim::query()->where('organisation_id', $brand->organisation_id)
            ->where('brand_id', $brand->id)->where('claim_text', $draftText)->first();
        if ($claim === null) {
            $claim = BrandClaim::factory()->forBrand($brand)->create([
                'claim_text' => $draftText, 'status' => 'DRAFT', 'claim_category' => 'PRODUCT_CAPABILITY',
            ]);
        }
        $hasEvidence = BrandClaimEvidence::query()->where('organisation_id', $brand->organisation_id)
            ->where('brand_id', $brand->id)->where('claim_id', $claim->id)
            ->where('knowledge_source_id', $source->id)->exists();
        if (! $hasEvidence) {
            BrandClaimEvidence::factory()->forBrandClaim($claim)->create([
                'knowledge_source_id' => $source->id, 'review_state' => 'PENDING',
                'supporting_excerpt' => 'Demo-only excerpt, not independently verified.',
            ]);
        }
    }
}
