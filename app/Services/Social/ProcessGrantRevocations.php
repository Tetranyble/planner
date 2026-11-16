<?php

namespace App\Services\Social;

use App\Repository\Contracts\ProviderGrantRevocationRepositoryContract;
use App\Social\Providers\TikTokGrantRevoker;
use Throwable;

/** Provider calls occur outside DB transactions; ambiguous responses are never replayed. */
final class ProcessGrantRevocations
{
    public function __construct(private readonly ProviderGrantRevocationRepositoryContract $repo,
        private readonly TikTokGrantRevoker $tiktok) {}

    public function run(int $limit = 20): array
    {
        $stats = ['attempted' => 0, 'acknowledged' => 0, 'uncertain' => 0, 'skipped' => 0, 'disabled' => 0];
        if (! config('social_integrations.remote_revocation_enabled', false)) {
            $stats['disabled'] = 1;

            return $stats;
        }
        foreach ($this->repo->dueIds($limit) as $id) {
            $lease = $this->repo->acquire($id);
            if (! $lease) {
                $stats['skipped']++;

                continue;
            }
            $stats['attempted']++;
            try {
                if ($lease->provider !== 'tiktok') {
                    throw new \LogicException('Unsupported remote provider');
                }
                $this->tiktok->revoke($lease->accessToken);
                if ($this->repo->complete($lease)) {
                    $stats['acknowledged']++;
                } else {
                    $stats['uncertain']++;
                }
            } catch (Throwable) {
                // Includes timeouts, 429, 5xx and commit failures after provider ACK.
                $this->repo->uncertain($lease);
                $stats['uncertain']++;
            }
        }

        return $stats;
    }
}
