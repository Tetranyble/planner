<?php

namespace Database\Seeders;

use App\Models\ApprovalRequest;
use App\Models\Brand;
use App\Models\BrandApprovalPolicy;
use App\Models\ContentTarget;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Opt-in fixture. Never adds synthetic reviews in production or on a default seed. */
final class ContentApprovalDevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Content approval demonstration seeder is restricted to local/testing.');
        }
        $brand = Brand::query()->orderBy('id')->first();
        if (! $brand) {
            return;
        }
        $target = ContentTarget::query()->where('organisation_id', $brand->organisation_id)->where('brand_id', $brand->id)->first();
        $policy = BrandApprovalPolicy::query()->where('organisation_id', $brand->organisation_id)
            ->where('brand_id', $brand->id)->where('state', 'ACTIVE')->first();
        if (! $target || ! $policy) {
            return;
        }
        $owner = User::query()->whereKey($target->created_by_user_id)->first();
        if (! $owner) {
            return;
        }
        $exists = ApprovalRequest::query()->where('organisation_id', $brand->organisation_id)
            ->where('brand_id', $brand->id)->where('content_target_id', $target->id)->exists();
        if (! $exists) {
            ApprovalRequest::factory()->forTarget($target, $policy, $owner)->create();
        }
    }
}
