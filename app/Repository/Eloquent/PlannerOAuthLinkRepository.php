<?php

namespace App\Repository\Eloquent;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\PlannerAgentDelegation;
use App\Models\PlannerOAuthLink;
use App\Models\PlannerOAuthUser;
use App\Models\User;
use App\Repository\Contracts\PlannerOAuthLinkRepositoryContract;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class PlannerOAuthLinkRepository implements PlannerOAuthLinkRepositoryContract
{
    public function availableDelegations(User $actor): array
    {
        return PlannerAgentDelegation::query()->where('user_id', $actor->id)
            ->whereNull('revoked_at')->where('expires_at', '>', now())
            ->orderByDesc('id')->limit(50)->get()->all();
    }

    public function delegationForConsent(User $actor, int $delegationId): ?PlannerAgentDelegation
    {
        return PlannerAgentDelegation::query()->whereKey($delegationId)
            ->where('user_id', $actor->id)->whereNull('revoked_at')
            ->where('expires_at', '>', now())->first();
    }

    public function bind(User $actor, string $clientId, PlannerAgentDelegation $delegation): PlannerOAuthLink
    {
        return DB::transaction(function () use ($actor, $clientId, $delegation): PlannerOAuthLink {
            $existing = PlannerOAuthLink::query()->where('oauth_client_id', $clientId)
                ->where('user_id', $actor->id)->lockForUpdate()->first();
            if ($existing !== null) {
                if ((int) $existing->delegation_id !== (int) $delegation->id) {
                    throw new ConflictHttpException('A client cannot change its bound delegation. Register a new client.');
                }
                // Revocation is permanent for old tokens; a new explicit consent may
                // reconnect the SAME delegation without resurrecting any revoked token.
                if ($existing->revoked_at !== null) {
                    $existing->forceFill(['revoked_at' => null])->save();
                }

                return $existing;
            }

            return PlannerOAuthLink::query()->create([
                'oauth_client_id' => $clientId, 'user_id' => $actor->id,
                'organisation_id' => $delegation->organisation_id, 'brand_id' => $delegation->brand_id,
                'delegation_id' => $delegation->id,
            ]);
        }, 3);
    }

    public function scopeForPassportToken(PlannerOAuthUser $actor, string $clientId): ?array
    {
        $link = PlannerOAuthLink::query()->where('oauth_client_id', $clientId)
            ->where('user_id', $actor->id)->whereNull('revoked_at')->first();
        if ($link === null) {
            return null;
        }
        $delegation = PlannerAgentDelegation::query()->whereKey($link->delegation_id)
            ->where('user_id', $actor->id)->where('organisation_id', $link->organisation_id)
            ->where('brand_id', $link->brand_id)->whereNull('revoked_at')
            ->where('expires_at', '>', now())->first();
        if ($delegation === null) {
            return null;
        }
        $organisation = Organisation::query()->find($link->organisation_id);
        $brand = Brand::query()->whereKey($link->brand_id)
            ->where('organisation_id', $link->organisation_id)->first();

        return $organisation && $brand
            ? ['organisation' => $organisation, 'brand' => $brand, 'delegation' => $delegation]
            : null;
    }

    public function listForUser(User $actor): array
    {
        return PlannerOAuthLink::query()->where('user_id', $actor->id)->latest('id')->limit(100)
            ->get()->map(fn (PlannerOAuthLink $link): array => [
                'id' => $link->id, 'client_id' => $link->oauth_client_id,
                'organisation_id' => $link->organisation_id, 'brand_id' => $link->brand_id,
                'delegation_id' => $link->delegation_id,
                'revoked' => $link->revoked_at !== null,
                'created_at' => $link->created_at?->toIso8601String(),
            ])->all();
    }

    public function revoke(User $actor, int $linkId): bool
    {
        return DB::transaction(function () use ($actor, $linkId): bool {
            $link = PlannerOAuthLink::query()->whereKey($linkId)->where('user_id', $actor->id)
                ->lockForUpdate()->first();
            if ($link === null) {
                return false;
            }
            if ($link->revoked_at !== null) {
                return true;
            }
            $ids = Token::query()->where('client_id', $link->oauth_client_id)
                ->where('user_id', $actor->id)->pluck('id')->all();
            if ($ids !== []) {
                RefreshToken::query()->whereIn('access_token_id', $ids)->update(['revoked' => true]);
                Token::query()->whereIn('id', $ids)->update(['revoked' => true]);
            }
            $link->forceFill(['revoked_at' => now()])->save();

            return true;
        }, 3);
    }
}
