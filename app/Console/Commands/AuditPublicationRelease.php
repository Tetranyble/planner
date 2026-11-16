<?php

namespace App\Console\Commands;

use App\Services\Publishing\ExecutePublicationPlans;
use Illuminate\Console\Command;

/** Read-only; prints no tokens, actor IDs, application secrets or untrusted URLs. */
final class AuditPublicationRelease extends Command
{
    protected $signature = 'planner:audit-publication-release {--json : Machine-readable sanitized gates} {--strict : Return failure unless all release gates and a provider entitlement are satisfied}';

    protected $description = 'Audit fail-closed provider publishing release prerequisites without provider calls';

    public function handle(): int
    {
        $entitled = [];
        foreach (['facebook', 'linkedin', 'x'] as $provider) {
            $entitled[$provider] = (bool) config('social_integrations.publishing_entitlements.'.$provider, false);
        }
        $gates = [
            'production_environment' => app()->environment('production'),
            'text_execution_enabled' => (bool) config('planner.text_dispatch_enabled', false),
            'provider_approval_attested' => (bool) config('planner.text_dispatch_provider_approved', false),
            'independent_release_switch' => (bool) config('planner.text_dispatch_released', false),
            'at_least_one_provider_entitled' => in_array(true, $entitled, true),
        ];
        $enabled = ExecutePublicationPlans::isEnabled() && $gates['at_least_one_provider_entitled'];
        if ($this->option('json')) {
            $this->line(json_encode(['gates' => $gates, 'providers' => $entitled,
                'release_eligible' => $enabled, 'automatic_replay' => false, 'media_delivery_enabled' => false,
                'live_api_verification' => 'PENDING'], JSON_THROW_ON_ERROR));
        } else {
            $rows = [];
            foreach ($gates as $label => $value) {
                $rows[] = [$label, $value ? 'YES' : 'NO'];
            }
            foreach ($entitled as $provider => $allowed) {
                $rows[] = ['provider_entitlement_'.$provider, $allowed ? 'ATTESTED' : 'BLOCKED'];
            }
            $rows[] = ['release_eligible', $enabled ? 'YES' : 'NO'];
            $rows[] = ['automatic replay of ambiguous sends', 'NEVER'];
            $rows[] = ['Instagram / TikTok / YouTube media delivery', 'NOT RELEASED'];
            $rows[] = ['Remote credentials and API compatibility', 'NOT VERIFIED'];
            $this->table(['Release requirement', 'Status'], $rows);
            $this->comment('No provider connection or posting action was attempted; entitlement flags must reflect actual app review.');
        }

        return ($this->option('strict') && ! $enabled) ? self::FAILURE : self::SUCCESS;
    }
}
