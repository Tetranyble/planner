<?php

namespace App\Console\Commands;

use App\Services\Automation\ManageReportAutomations;
use Illuminate\Console\Command;

/** Only computes internal, unverified-evidence reports; does not publish or call social APIs. */
final class ProcessScheduledReportAutomations extends Command
{
    protected $signature = 'planner:process-scheduled-reports {--limit=25} {--dry-run}';

    protected $description = 'Process opt-in daily UTC social metric reports (no provider I/O)';

    public function handle(ManageReportAutomations $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        if (! $dryRun && ! config('planner.report_scheduler_enabled', false)) {
            $this->error('Report scheduler disabled. Configure PLANNER_REPORT_SCHEDULER_ENABLED=true after validation.');

            return self::FAILURE;
        }
        $result = $service->processDue((int) $this->option('limit'), $dryRun);
        $this->line(json_encode($result, JSON_THROW_ON_ERROR));

        return $result['errors'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
