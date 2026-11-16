<?php

namespace App\Console\Commands;

use App\Repository\Contracts\PublicationOperationsRepositoryContract;
use Illuminate\Console\Command;

/** Stale leases are quarantined, NEVER automatically re-queued or sent. */
final class RecoverPublicationLeases extends Command
{
    protected $signature = 'planner:recover-publication-leases {--limit=50} {--dry-run}';

    protected $description = 'Quarantine expired publication leases without provider I/O';

    public function handle(PublicationOperationsRepositoryContract $repository): int
    {
        $limit = max(1, min(500, (int) $this->option('limit')));
        $ids = $repository->staleLeaseIds($limit);
        if ($this->option('dry-run')) {
            $this->info('Expired leases found: '.count($ids).' (dry run; no changes)');

            return self::SUCCESS;
        }
        $recovered = 0;
        foreach ($ids as $id) {
            if ($repository->recoverStale((int) $id) !== null) {
                $recovered++;
            }
        }
        $this->info('Quarantined expired publication leases: '.$recovered);

        return self::SUCCESS;
    }
}
