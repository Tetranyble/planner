<?php

namespace App\Repository\Eloquent;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\SocialOAuthSelection;
use App\Models\User;
use App\Repository\Contracts\SocialOAuthSelectionRepositoryContract;
use App\Social\Data\DiscoveredSocialAccount;
use App\Social\Data\OAuthGrant;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;

final class SocialOAuthSelectionRepository implements SocialOAuthSelectionRepositoryContract
{
    public function create(Organisation $organisation, Brand $brand, User $actor, string $provider, OAuthGrant $grant, array $accounts): SocialOAuthSelection
    {
        return SocialOAuthSelection::query()->create([
            'organisation_id' => $organisation->id, 'brand_id' => $brand->id,
            'user_id' => $actor->id, 'provider' => $provider,
            'meta_actor_digest' => in_array($provider, ['facebook', 'instagram'], true)
                ? hash_hmac('sha256', $grant->actorId, (string) config('social_integrations.meta_actor_hmac_key')) : null,
            'grant_ciphertext' => Crypt::encryptString(json_encode([
                'accessToken' => $grant->accessToken,
                'refreshToken' => $grant->refreshToken,
                'actorId' => $grant->actorId,
                'scopes' => $grant->scopes,
                'expiresIn' => $grant->expiresIn,
                'refreshExpiresIn' => $grant->refreshExpiresIn,
            ], JSON_THROW_ON_ERROR)),
            'candidates_ciphertext' => Crypt::encryptString(json_encode(array_map(
                fn (DiscoveredSocialAccount $account) => [
                    'externalId' => $account->externalId,
                    'kind' => $account->kind,
                    'displayName' => $account->displayName,
                    'handle' => $account->handle,
                ], $accounts,
            ), JSON_THROW_ON_ERROR)),
            'expires_at' => now()->addMinutes(10),
        ]);
    }

    public function findForActor(Organisation $organisation, Brand $brand, User $actor, string $selectionUuid): SocialOAuthSelection
    {
        $selection = SocialOAuthSelection::query()
            ->where('organisation_id', $organisation->id)->where('brand_id', $brand->id)
            ->where('user_id', $actor->id)->where('uuid', $selectionUuid)->first();
        if ($selection === null || $selection->consumed_at !== null || $selection->expires_at->isPast()) {
            throw new GoneHttpException('The account selection has expired or was already used. Reconnect the provider.');
        }

        return $selection;
    }

    public function lockForConsumption(Organisation $organisation, Brand $brand, User $actor, string $selectionUuid): SocialOAuthSelection
    {
        $selection = SocialOAuthSelection::query()->where('organisation_id', $organisation->id)
            ->where('brand_id', $brand->id)->where('user_id', $actor->id)
            ->where('uuid', $selectionUuid)->lockForUpdate()->first();
        if ($selection === null || $selection->consumed_at !== null || $selection->expires_at->isPast()) {
            throw new GoneHttpException('The account selection has expired or was already used. Reconnect the provider.');
        }

        return $selection;
    }

    public function accounts(SocialOAuthSelection $selection): array
    {
        return array_map(fn (array $item): DiscoveredSocialAccount => new DiscoveredSocialAccount(
            $item['externalId'], $item['kind'], $item['displayName'], $item['handle'] ?? null,
        ), json_decode(Crypt::decryptString($selection->candidates_ciphertext), true, flags: JSON_THROW_ON_ERROR));
    }

    public function grant(SocialOAuthSelection $selection): OAuthGrant
    {
        $item = json_decode(Crypt::decryptString($selection->grant_ciphertext), true, flags: JSON_THROW_ON_ERROR);

        return new OAuthGrant(
            $item['accessToken'], $item['refreshToken'], $item['actorId'], $item['scopes'],
            $item['expiresIn'], $item['refreshExpiresIn'],
        );
    }

    public function markConsumed(SocialOAuthSelection $selection): void
    {
        // Wipe tokens as soon as selection has completed; consumed rows remain for audit only.
        $selection->forceFill([
            'consumed_at' => now(),
            'grant_ciphertext' => Crypt::encryptString('purged'),
            'candidates_ciphertext' => Crypt::encryptString('purged'),
        ])->save();
    }

    public function pruneExpired(): int
    {
        // Remove encrypted temporary grants quickly, independently of the marketing data retention lifecycle.
        return SocialOAuthSelection::query()
            ->where('expires_at', '<', now()->subHour())
            ->orWhere('consumed_at', '<', now()->subHour())
            ->delete();
    }
}
