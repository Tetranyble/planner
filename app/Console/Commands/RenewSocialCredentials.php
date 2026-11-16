<?php

namespace App\Console\Commands;

use App\Services\Social\RenewSocialCredentials as CredentialRenewal;
use Illuminate\Console\Command;

final class RenewSocialCredentials extends Command
{
    protected $signature = 'planner:renew-social-credentials {--limit=20} {--lead-minutes=20}';

    protected $description = 'Safely renew due social OAuth credentials; secrets never printed';

    public function handle(CredentialRenewal $renewal): int
    {
        $limit = (int) $this->option('limit');
        $lead = (int) $this->option('lead-minutes');
        if ($limit < 1 || $limit > 100 || $lead < 1 || $lead > 120) {
            $this->error('Limit must be 1..100 and lead-minutes must be 1..120.');

            return self::FAILURE;
        }
        $summary = $renewal->run($limit, $lead);
        $this->info(sprintf('Checked %d; renewed %d; reauthorization %d; deferred %d; skipped %d.',
            $summary['checked'], $summary['renewed'], $summary['reauthorization'], $summary['deferred'], $summary['skipped']));

        return self::SUCCESS;
    }
}
