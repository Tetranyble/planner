<?php

namespace App\Console\Commands;

use App\Repository\Contracts\PublicationIntentRepositoryContract;
use Illuminate\Console\Command;

/** Monitoring only. NEVER changes job status, leases or calls external platforms. */
final class InspectHeldPublicationIntents extends Command
{
    protected $signature = 'planner:inspect-held-publications {--limit=100}';

    protected $description = 'Count overdue held publication intents without dispatching them';

    public function handle(PublicationIntentRepositoryContract $repository): int
    {
        $limit = max(1, min((int) $this->option('limit'), 500));
        $due = $repository->dueHeld($limit);
        $this->components->info('Overdue held publication intentions: '.$due->count());

        return self::SUCCESS;
    }
}
