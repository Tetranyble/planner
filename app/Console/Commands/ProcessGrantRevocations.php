<?php

namespace App\Console\Commands;

use App\Services\Social\ProcessGrantRevocations as Processor;
use Illuminate\Console\Command;

final class ProcessGrantRevocations extends Command
{
    protected $signature = 'planner:process-grant-revocations {--limit=20}';

    protected $description = 'Process explicitly confirmed, safely scoped remote provider grant revocations';

    public function handle(Processor $service): int
    {
        $limit = (int) $this->option('limit');
        if ($limit < 1 || $limit > 100) {
            $this->error('limit must be 1..100');

            return self::FAILURE;
        }
        $result = $service->run($limit);
        $this->info(sprintf('Attempted %d, acknowledged %d, uncertain %d, skipped %d, disabled %d.',
            $result['attempted'], $result['acknowledged'], $result['uncertain'], $result['skipped'], $result['disabled']));

        return self::SUCCESS;
    }
}
