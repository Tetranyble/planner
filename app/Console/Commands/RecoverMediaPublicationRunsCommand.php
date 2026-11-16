<?php

namespace App\Console\Commands;

use App\Repository\Contracts\MediaPublicationRunRepositoryContract;
use Illuminate\Console\Command;

final class RecoverMediaPublicationRunsCommand extends Command
{
    protected $signature = 'planner:recover-media-publication-leases {--limit=50}';

    protected $description = 'Quarantine expired in-flight media stages, without retrying or contacting providers';

    public function handle(MediaPublicationRunRepositoryContract $repo): int
    {
        $this->line(json_encode($repo->quarantineExpired(max(1, min(100, (int) $this->option('limit')))), JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
