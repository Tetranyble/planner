<?php

namespace App\Console\Commands;

use App\Repository\Contracts\PlannerIntegrationGrantRepositoryContract;
use Illuminate\Console\Command;

final class PrunePlannerIntegrationGrants extends Command
{
    protected $signature = 'planner:prune-integration-grants {--limit=100 : Maximum expired grants}';

    protected $description = 'Delete expired Planner integration bearer credentials and preserve audit grant records';

    public function handle(PlannerIntegrationGrantRepositoryContract $grants): int
    {
        $this->line('Expired integration grants revoked: '.$grants->pruneExpired((int) $this->option('limit')));

        return self::SUCCESS;
    }
}
