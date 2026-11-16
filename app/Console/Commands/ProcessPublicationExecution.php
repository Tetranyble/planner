<?php

namespace App\Console\Commands;

use App\Services\Publishing\ExecutePublicationPlans;
use Illuminate\Console\Command;

final class ProcessPublicationExecution extends Command
{
    protected $signature = 'planner:process-publication-execution {--limit=20}';

    protected $description = 'Execute due, operator-armed text publications only when all production controls are released';

    public function handle(ExecutePublicationPlans $service): int
    {
        $result = $service->process(max(1, min(100, (int) $this->option('limit'))));
        $this->line(json_encode($result, JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
