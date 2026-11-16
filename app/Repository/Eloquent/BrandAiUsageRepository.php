<?php

namespace App\Repository\Eloquent;

use App\Domain\Ai\VerifiedTokenCostEstimate;
use App\Models\Brand;
use App\Models\BrandAiUsageReservation;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\BrandAiUsageRepositoryContract;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class BrandAiUsageRepository implements BrandAiUsageRepositoryContract
{
    private function scope(Organisation $org, Brand $brand)
    {
        return BrandAiUsageReservation::query()->where('organisation_id', $org->id)
            ->where('brand_id', $brand->id)->whereDate('usage_day', now('UTC')->toDateString());
    }

    public function reserve(Organisation $org, Brand $brand, User $actor, string $purpose, int $limit): BrandAiUsageReservation
    {
        // Brand row lock is taken by the caller: serialize reservations across requests and workers.
        $used = (clone $this->scope($org, $brand))->whereIn('status', ['RESERVED', 'SUCCEEDED', 'UNCERTAIN'])->count();
        if ($used >= $limit) {
            throw ValidationException::withMessages(['ai' => 'Daily brand AI generation limit reached. Try tomorrow (UTC).']);
        }

        return BrandAiUsageReservation::query()->forceCreate([
            'uuid' => (string) Str::uuid(), 'organisation_id' => $org->id, 'brand_id' => $brand->id,
            'requested_by_user_id' => $actor->id, 'usage_day' => now('UTC')->toDateString(),
            'purpose' => $purpose, 'status' => 'RESERVED', 'expires_at' => now('UTC')->addMinutes(10),
        ]);
    }

    public function settle(BrandAiUsageReservation $reservation, bool $success, ?string $model, ?string $hash, ?array $usage = null): void
    {
        $row = BrandAiUsageReservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();
        if ($row->status !== 'RESERVED') {
            return;
        } // stale worker cannot override a settled record
        $estimate = $success ? VerifiedTokenCostEstimate::calculate($model, $usage, VerifiedTokenCostEstimate::configured(),
            (string) config('services.planner_ai.cost_rate_revision', '')) : null;
        $row->forceFill(['status' => $success ? 'SUCCEEDED' : 'FAILED', 'completed_at' => now('UTC'),
            'model' => $success ? $model : null, 'output_hash' => $success ? $hash : null,
            'provider_input_tokens' => $success ? ($usage['input_tokens'] ?? null) : null,
            'provider_output_tokens' => $success ? ($usage['output_tokens'] ?? null) : null,
            'estimated_micro_usd' => $estimate['micro_usd'] ?? null,
            'cost_rate_revision' => $estimate['revision'] ?? null])->save();
    }

    /** An ambiguous provider attempt may already have incurred a charge; do not replenish its daily quota. */
    public function markUncertain(BrandAiUsageReservation $reservation): void
    {
        $row = BrandAiUsageReservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();
        if ($row->status !== 'RESERVED') {
            return;
        }
        $row->forceFill(['status' => 'UNCERTAIN', 'completed_at' => now('UTC')])->save();
    }

    public function usage(Organisation $org, Brand $brand, int $limit): array
    {
        $rows = $this->scope($org, $brand)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $s = (int) ($rows['SUCCEEDED'] ?? 0);
        $r = (int) ($rows['RESERVED'] ?? 0);
        $u = (int) ($rows['UNCERTAIN'] ?? 0);

        return ['day_utc' => now('UTC')->toDateString(), 'limit' => $limit, 'succeeded' => $s, 'reserved' => $r,
            'uncertain' => $u, 'remaining' => max(0, $limit - $s - $r - $u), 'failed' => (int) ($rows['FAILED'] ?? 0),
            'provider_token_evidence' => [
                'input_tokens_observed' => (int) (clone $this->scope($org, $brand))->where('status', 'SUCCEEDED')->sum('provider_input_tokens'),
                'output_tokens_observed' => (int) (clone $this->scope($org, $brand))->where('status', 'SUCCEEDED')->sum('provider_output_tokens'),
                'usage_unreported_count' => (int) (clone $this->scope($org, $brand))->where('status', 'SUCCEEDED')
                    ->where(fn ($q) => $q->whereNull('provider_input_tokens')->orWhereNull('provider_output_tokens'))->count(),
                'cost_estimate' => [
                    'micro_usd_observed' => (int) (clone $this->scope($org, $brand))->where('status', 'SUCCEEDED')->sum('estimated_micro_usd'),
                    'priced_generation_count' => (int) (clone $this->scope($org, $brand))->where('status', 'SUCCEEDED')->whereNotNull('estimated_micro_usd')->count(),
                    'unpriced_generation_count' => (int) (clone $this->scope($org, $brand))->where('status', 'SUCCEEDED')->whereNull('estimated_micro_usd')->count(),
                    'estimate_only' => true, 'not_provider_invoice' => true,
                ],
                'currency_cost_available' => false,
            ]];
    }
}
