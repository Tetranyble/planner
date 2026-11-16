<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Services\Integrations\OperationalAlertMonitor;
use Illuminate\Console\Command;

/** Bounded read-only observation of failures with append-only alert evidence. */
final class ScanPlannerOperationalAlerts extends Command
{
    protected $signature = 'planner:scan-operational-alerts {--limit=1000 : Maximum brands} {--after-id=0 : Resume scanning after a brand ID}';

    protected $description = 'Record brand-isolated operational alert state without provider requests or publication retries';

    public function handle(OperationalAlertMonitor $monitor): int
    {
        if (! config('planner.operational_alert_scan_enabled', false)) {
            $this->comment('Operational alert scanning is disabled.');

            return self::SUCCESS;
        }
        $limit = max(1, min(10000, (int) $this->option('limit')));
        $after = max(0, (int) $this->option('after-id'));
        $checked = 0;
        $changed = 0;
        $lastId = $after;
        foreach (Brand::query()->active()->where('id', '>', $after)
            ->whereHas('organisation', fn ($q) => $q->active())
            ->orderBy('id')->limit($limit)->with('organisation')->get() as $brand) {
            $changed += $monitor->check($brand->organisation, $brand);
            $checked++;
            $lastId = (int) $brand->id;
        }
        $this->line(json_encode(['checked' => $checked, 'state_changes' => $changed,
            'last_brand_id' => $lastId, 'next_after_id' => $lastId], JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
