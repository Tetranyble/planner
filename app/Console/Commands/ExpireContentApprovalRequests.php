<?php

namespace App\Console\Commands;

use App\Services\Content\ManageContentApprovals;
use Illuminate\Console\Command;

final class ExpireContentApprovalRequests extends Command
{
    protected $signature = 'planner:expire-content-approvals {--limit=100 : Maximum pending requests to expire per run}';

    protected $description = 'Expire outdated content review requests (does not publish content)';

    public function handle(ManageContentApprovals $approvals): int
    {
        $limit = (int) $this->option('limit');
        if ($limit < 1 || $limit > 500) {
            $this->error('Limit must be between 1 and 500.');

            return self::FAILURE;
        }
        $this->info('Expired '.$approvals->expireDue($limit).' approval requests.');

        return self::SUCCESS;
    }
}
