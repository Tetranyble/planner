<?php

namespace App\Console\Commands;

use App\Services\Social\HandleMetaDataDeletion;
use Illuminate\Console\Command;

final class ProcessMetaDataDeletions extends Command
{
    protected $signature = 'planner:process-meta-deletions {--limit=20 : Maximum pending requests to process}';

    protected $description = 'Process signed Meta privacy requests; complete only after provider-derived data is erased.';

    public function handle(HandleMetaDataDeletion $deletions): int
    {
        $result = $deletions->processPending((int) $this->option('limit'));
        $this->components->info(sprintf('Attempted %d; complete %d; review %d; failed %d.',
            $result['attempted'], $result['completed'], $result['needs_review'], $result['failed']));

        return $result['failed'] ? self::FAILURE : self::SUCCESS;
    }
}
