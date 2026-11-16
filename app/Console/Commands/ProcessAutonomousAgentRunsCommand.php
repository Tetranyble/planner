<?php

namespace App\Console\Commands;

use App\Services\Agents\ProcessAutonomousAgentRuns;
use Illuminate\Console\Command;

final class ProcessAutonomousAgentRunsCommand extends Command
{
    protected $signature = 'planner:process-agent-runs {--limit=5 : Maximum stages to process}';

    protected $description = 'Process opted-in, durable autonomous research and resource stages without publication';

    public function handle(ProcessAutonomousAgentRuns $worker): int
    {
        $result = $worker->run(min(20, max(1, (int) $this->option('limit'))));
        $this->line(json_encode($result, JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
