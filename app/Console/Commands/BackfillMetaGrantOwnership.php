<?php

namespace App\Console\Commands;

use App\Models\ProviderConnection;
use App\Repository\Contracts\ProviderGrantOwnershipRepositoryContract;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Idempotent, explicit backfill for historical Meta grant ownership. */
final class BackfillMetaGrantOwnership extends Command
{
    protected $signature = 'planner:backfill-meta-grants {--apply : Persist grants (default is dry-run)} {--limit=500 : Max legacy connections to inspect}';

    protected $description = 'Map Facebook/Instagram app-scoped grants to ownership groups without revealing actor IDs';

    public function handle(ProviderGrantOwnershipRepositoryContract $ownership): int
    {
        if ((string) config('social_integrations.providers.facebook.client_id') === ''
            || (string) config('social_integrations.meta_actor_hmac_key') === '') {
            $this->error('META_APP_ID and stable META_ACTOR_HMAC_KEY must be configured.');

            return self::FAILURE;
        }
        $count = 0;
        $mapped = 0;
        $limit = max(1, min((int) $this->option('limit'), 10000));
        ProviderConnection::query()->whereIn('provider', ['facebook', 'instagram'])
            ->whereNotNull('external_actor_id')->orderBy('id')->chunkById(100, function ($connections) use (&$count, &$mapped, $limit, $ownership): bool {
                foreach ($connections as $connection) {
                    if ($count >= $limit) {
                        return false;
                    }
                    $count++;
                    $actor = (string) $connection->external_actor_id;
                    // Exclude anonymized identities and fallback channel identities.
                    if ($actor === '' || str_starts_with($actor, 'erased:') || str_starts_with($actor, 'channel:')) {
                        continue;
                    }
                    $mapped++;
                    if ($this->option('apply')) {
                        DB::transaction(fn () => $ownership->linkMeta($connection, $actor), attempts: 3);
                    }
                }

                return true;
            });
        $this->info(sprintf('Examined %d historical connections; %d eligible; mode: %s.',
            $count, $mapped, $this->option('apply') ? 'applied' : 'dry run'));

        return self::SUCCESS;
    }
}
