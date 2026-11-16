<?php

namespace App\Console\Commands;

use App\Services\Scheduling\ExpandRecurringCampaignOccurrences;
use Illuminate\Console\Command;

/** Bounded review projections, deliberately unrelated to provider publishing. */
final class ExpandRecurringCampaigns extends Command
{
    protected $signature = 'planner:expand-recurring-campaigns {--limit=25}';

    protected $description = 'Project upcoming recurring campaign slots for human review only';

    public function handle(ExpandRecurringCampaignOccurrences $expander): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);
        if ($limit === false || $limit < 1 || $limit > 100) {
            $this->error('Limit must be between 1 and 100.');

            return self::FAILURE;
        }
        $result = $expander->run($limit);
        $this->line(json_encode($result, JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
