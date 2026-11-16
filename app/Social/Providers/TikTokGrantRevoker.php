<?php

namespace App\Social\Providers;

use App\Social\Contracts\RemoteGrantRevoker;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Documented TikTok Login Kit revoke endpoint. Not used by the local disconnect action. */
final class TikTokGrantRevoker implements RemoteGrantRevoker
{
    public function provider(): string
    {
        return 'tiktok';
    }

    public function revoke(string $accessToken): void
    {
        $key = (string) config('social_integrations.providers.tiktok.client_id');
        $secret = (string) config('social_integrations.providers.tiktok.client_secret');
        if ($key === '' || $secret === '') {
            throw new RuntimeException('PROVIDER_NOT_CONFIGURED');
        }
        $response = Http::asForm()->acceptJson()->timeout(12)->post('https://open.tiktokapis.com/v2/oauth/revoke/', [
            'client_key' => $key, 'client_secret' => $secret, 'token' => $accessToken,
        ]);
        // No retries on timeout: provider may have consumed grant already.
        if (! $response->successful() || ($response->json('error') !== null)) {
            throw new RuntimeException('PROVIDER_RESULT_UNCERTAIN');
        }
    }
}
