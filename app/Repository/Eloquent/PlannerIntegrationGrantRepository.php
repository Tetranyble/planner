<?php

namespace App\Repository\Eloquent;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\PlannerIntegrationGrant;
use App\Models\User;
use App\Repository\Contracts\PlannerIntegrationGrantRepositoryContract;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

final class PlannerIntegrationGrantRepository implements PlannerIntegrationGrantRepositoryContract
{
    public function issue(Organisation $org, Brand $brand, User $owner, string $name, int $days): array
    {
        return DB::transaction(static function () use ($org, $brand, $owner, $name, $days): array {
            $expiry = now()->addDays($days);
            $token = $owner->createToken('planner-integration:'.(int) $brand->id.':'.$name,
                ['planner:read'], $expiry);
            $grant = PlannerIntegrationGrant::query()->create([
                'organisation_id' => $org->id, 'brand_id' => $brand->id, 'user_id' => $owner->id,
                'sanctum_token_id' => $token->accessToken->getKey(), 'name' => $name, 'expires_at' => $expiry,
            ]);

            return ['grant' => $grant, 'plain_text_token' => $token->plainTextToken];
        });
    }

    public function list(Organisation $org, Brand $brand, User $owner): array
    {
        return PlannerIntegrationGrant::query()->where('organisation_id', $org->id)
            ->where('brand_id', $brand->id)->where('user_id', $owner->id)
            ->orderByDesc('id')->limit(50)->get(['id', 'name', 'expires_at', 'revoked_at', 'created_at'])
            ->map(static fn (PlannerIntegrationGrant $row): array => [
                'id' => $row->id, 'name' => $row->name, 'expires_at' => $row->expires_at?->toIso8601String(),
                'revoked_at' => $row->revoked_at?->toIso8601String(), 'created_at' => $row->created_at?->toIso8601String(),
            ])->all();
    }

    public function revoke(Organisation $org, Brand $brand, User $owner, int $grantId): bool
    {
        return DB::transaction(static function () use ($org, $brand, $owner, $grantId): bool {
            $row = PlannerIntegrationGrant::query()->whereKey($grantId)->where('organisation_id', $org->id)
                ->where('brand_id', $brand->id)->where('user_id', $owner->id)->lockForUpdate()->first();
            if ($row === null) {
                return false;
            }
            if ($row->revoked_at !== null) {
                return true;
            }
            // Revoke at Sanctum token source before recording the grant as revoked.
            $token = PersonalAccessToken::find($row->sanctum_token_id);
            $token?->delete();
            $row->forceFill(['revoked_at' => now()])->save();

            return true;
        });
    }

    /** Expired access has already been denied by middleware; cleanup deletes only integration tokens. */
    public function pruneExpired(int $limit): int
    {
        $count = 0;
        $ids = PlannerIntegrationGrant::query()->whereNull('revoked_at')
            ->where('expires_at', '<=', now())->orderBy('id')->limit(max(1, min(100, $limit)))
            ->pluck('id')->all();
        foreach ($ids as $id) {
            DB::transaction(static function () use ($id, &$count): void {
                $row = PlannerIntegrationGrant::query()->whereKey($id)->lockForUpdate()->first();
                if (! $row || $row->revoked_at !== null || $row->expires_at->isFuture()) {
                    return;
                }
                PersonalAccessToken::find($row->sanctum_token_id)?->delete();
                $row->forceFill(['revoked_at' => now()])->save();
                $count++;
            });
        }

        return $count;
    }

    /** Resolve the single current grant scope; no client-supplied organisation/brand accepted. */
    public function scopeForToken(int $sanctumTokenId, User $owner): ?array
    {
        $grant = PlannerIntegrationGrant::query()
            ->where('sanctum_token_id', $sanctumTokenId)
            ->where('user_id', $owner->getKey())
            ->whereNull('revoked_at')->where('expires_at', '>', now())->first();
        if ($grant === null) {
            return null;
        }
        $organisation = Organisation::query()->find($grant->organisation_id);
        $brand = Brand::query()->whereKey($grant->brand_id)
            ->where('organisation_id', $grant->organisation_id)->first();
        if ($organisation === null || $brand === null) {
            return null;
        }

        return ['organisation' => $organisation, 'brand' => $brand];
    }

    public function allows(int $sanctumTokenId, Organisation $org, Brand $brand, User $owner): bool
    {
        return PlannerIntegrationGrant::query()->where('sanctum_token_id', $sanctumTokenId)
            ->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->where('user_id', $owner->id)->whereNull('revoked_at')
            ->where('expires_at','>',now())->exists();
    }
}
