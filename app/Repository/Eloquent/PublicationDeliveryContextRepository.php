<?php

namespace App\Repository\Eloquent;

use App\Domain\Publishing\LiveContracts\PublishingScopePolicy;
use App\Models\Brand;
use App\Models\ContentTarget;
use App\Models\Organisation;
use App\Models\ProviderConnection;
use App\Models\ProviderConnectionCredential;
use App\Models\PublicationJob;
use App\Repository\Contracts\PublicationDeliveryContextRepositoryContract;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class PublicationDeliveryContextRepository implements PublicationDeliveryContextRepositoryContract
{
    public function manifest(Organisation $organisation, Brand $brand, PublicationJob $job): array
    {
        $target = $this->target($organisation, $brand, $job);
        $account = $target->account;
        if (! $account || (int) $account->organisation_id !== (int) $organisation->id
            || (int) $account->brand_id !== (int) $brand->id) {
            throw new ConflictHttpException('Publication target account is unavailable.');
        }

        return ['provider' => (string) $account->provider, 'external_account_id' => (string) $account->external_account_id,
            'caption' => (string) $target->platform_caption, 'has_media' => $target->version->mediaLinks->isNotEmpty(),
            'account_uuid' => (string) $account->uuid, 'mode' => (string) $target->publishing_mode, 'account_kind' => (string) $account->account_kind,
            'account_active' => $account->status === 'ACTIVE'];
    }

    public function accessToken(Organisation $organisation, Brand $brand, PublicationJob $job): string
    {
        $account = $this->target($organisation, $brand, $job)->account;
        if (! $account || (int) $account->organisation_id !== (int) $organisation->id || (int) $account->brand_id !== (int) $brand->id) {
            throw new ConflictHttpException('Provider account mismatch.');
        }
        $connection = ProviderConnection::query()->where('organisation_id', $organisation->id)
            ->whereKey($account->provider_connection_id)->where('provider', $account->provider)->where('status', 'CONNECTED')->first();
        if (! $connection || ! $connection->token_expires_at || $connection->token_expires_at->lessThanOrEqualTo(now()->addMinutes(2))) {
            throw new ConflictHttpException('Provider connection is not healthy.');
        }
        if (! PublishingScopePolicy::permitted((string) $account->provider, (string) $account->account_kind,
            is_array($connection->scopes) ? $connection->scopes : [])) {
            throw new ConflictHttpException('Provider grant lacks verified publishing entitlement.');
        }
        $credential = ProviderConnectionCredential::query()->where('organisation_id', $organisation->id)
            ->where('provider_connection_id', $connection->id)->first();
        if (! $credential?->access_token_ciphertext) {
            throw new ConflictHttpException('Provider credential is unavailable.');
        }
        try {
            $plaintext = Crypt::decryptString($credential->access_token_ciphertext);
        } catch (\Throwable) {
            throw new ConflictHttpException('Provider credential cannot be decrypted.');
        }
        if ($plaintext === '' || strlen($plaintext) > 8192) {
            throw new ConflictHttpException('Provider credential is invalid.');
        }

        return $plaintext;
    }

    private function target(Organisation $organisation, Brand $brand, PublicationJob $job): ContentTarget
    {
        if ((int) $job->organisation_id !== (int) $organisation->id || (int) $job->brand_id !== (int) $brand->id) {
            throw new ConflictHttpException('Publication tenant mismatch.');
        }

        return ContentTarget::query()->where('organisation_id', $organisation->id)->where('brand_id', $brand->id)
            ->whereKey($job->content_target_id)->with(['version.mediaLinks', 'account'])->firstOrFail();
    }
}
