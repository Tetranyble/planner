<?php

namespace App\Repository\Eloquent;

use App\Models\Brand;
use App\Models\MediaPublicationRun;
use App\Models\MediaTikTokConsent;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\MediaTikTokConsentRepositoryContract;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class MediaTikTokConsentRepository implements MediaTikTokConsentRepositoryContract
{
    public function find(Organisation $org, Brand $brand, MediaPublicationRun $run): ?MediaTikTokConsent
    {
        return MediaTikTokConsent::query()->where('organisation_id', $org->id)
            ->where('brand_id', $brand->id)->where('media_publication_run_id', $run->id)->first();
    }

    public function create(Organisation $org, Brand $brand, MediaPublicationRun $run, User $actor,
        array $selection, array $verifiedOptions, string $grantFingerprint, string $approvalHash, string $mediaSha): MediaTikTokConsent
    {
        $existing = MediaTikTokConsent::query()->where('organisation_id', $org->id)
            ->where('brand_id', $brand->id)->where('media_publication_run_id', $run->id)->lockForUpdate()->first();
        if ($existing) {
            if ($existing->privacy_selection !== $selection || $existing->approval_payload_hash !== $approvalHash
                || $existing->media_sha256 !== $mediaSha || $existing->grant_fingerprint !== $grantFingerprint) {
                throw new ConflictHttpException('TikTok consent is immutable. Make a new publication plan to change privacy.');
            }

            return $existing;
        }
        $record = (new MediaTikTokConsent)->forceFill([
            'organisation_id' => $org->id, 'brand_id' => $brand->id,
            'media_publication_run_id' => $run->id, 'consenting_user_id' => $actor->id,
            'privacy_selection' => $selection, 'verified_creator_options' => $verifiedOptions,
            'grant_fingerprint' => $grantFingerprint, 'approval_payload_hash' => $approvalHash,
            'media_sha256' => $mediaSha, 'verified_at' => now(),
        ]);
        $record->save();

        return $record;
    }
}
