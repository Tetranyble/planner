<?php

namespace App\Repository\Eloquent;

use App\Models\AutomationRule;
use App\Models\AutomationRuleVersion;
use App\Models\AutomationRun;
use App\Models\Brand;
use App\Models\Organisation;
use App\Repository\Contracts\ReportAutomationRepositoryContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class ReportAutomationRepository implements ReportAutomationRepositoryContract
{
    public function scheduledCandidates(int $limit): Collection
    {
        return AutomationRule::query()->where('trigger_kind', 'DAILY_UTC')->where('status', 'ENABLED')
            ->orderBy('id')->limit($limit)->get();
    }

    public function rules(Organisation $organisation, Brand $brand): Collection
    {
        return AutomationRule::query()->where('organisation_id', $organisation->id)->where('brand_id', $brand->id)->orderByDesc('id')->limit(100)->get();
    }

    public function rule(Organisation $organisation, Brand $brand, int $id, bool $lock = false): ?AutomationRule
    {
        $q = AutomationRule::query()->where('organisation_id', $organisation->id)->where('brand_id', $brand->id)->whereKey($id);

        return ($lock ? $q->lockForUpdate() : $q)->first();
    }

    public function createRule(Organisation $organisation, Brand $brand, int $actorId, string $name, int $dailyLimit): AutomationRule
    {
        $row = new AutomationRule;
        $row->forceFill(['organisation_id' => $organisation->id, 'brand_id' => $brand->id, 'owner_user_id' => $actorId, 'name' => $name,
            'max_runs_per_day' => $dailyLimit, 'trigger_kind' => 'MANUAL', 'status' => 'DRAFT'])->save();

        return $row;
    }

    public function createVersion(Organisation $organisation, Brand $brand, AutomationRule $rule, int $actorId, array $config, string $hash): AutomationRuleVersion
    {
        $row = new AutomationRuleVersion;
        $row->forceFill(['uuid' => (string) Str::uuid(), 'organisation_id' => $organisation->id, 'brand_id' => $brand->id,
            'automation_rule_id' => $rule->id, 'version_number' => 1, 'trigger_config' => json_encode(($config['trigger_kind'] ?? 'MANUAL') === 'DAILY_UTC' ? ['kind' => 'DAILY_UTC', 'hour_utc' => (int) $config['hour_utc']] : ['kind' => 'MANUAL'], JSON_THROW_ON_ERROR),
            'condition_config' => json_encode(($config['trigger_kind'] ?? 'MANUAL') === 'DAILY_UTC' ? ['lookback_days' => (int) $config['lookback_days']] : ['from' => $config['from'], 'to' => $config['to']], JSON_THROW_ON_ERROR),
            'action_plan' => json_encode(['kind' => 'METRICS_REPORT_ONLY'], JSON_THROW_ON_ERROR),
            'policy_snapshot' => json_encode(['source' => 'USER_REPORTED_UNVERIFIED', 'publishing_allowed' => false], JSON_THROW_ON_ERROR),
            'config_hash' => $hash, 'created_by_user_id' => $actorId])->save();
        if (($config['trigger_kind'] ?? 'MANUAL') === 'DAILY_UTC') {
            $rule->forceFill(['trigger_kind' => 'DAILY_UTC'])->save();
        }

        return $row;
    }

    public function setStatus(AutomationRule $rule, string $status): void
    {
        $rule->forceFill(['status' => $status])->save();
    }

    public function latestVersion(Organisation $organisation, Brand $brand, AutomationRule $rule): ?AutomationRuleVersion
    {
        return AutomationRuleVersion::query()->where('organisation_id', $organisation->id)->where('brand_id', $brand->id)
            ->where('automation_rule_id', $rule->id)->orderByDesc('version_number')->first();
    }

    public function run(Organisation $organisation, Brand $brand, AutomationRuleVersion $version, string $key): ?AutomationRun
    {
        return AutomationRun::query()->where('organisation_id', $organisation->id)->where('brand_id', $brand->id)
            ->where('automation_rule_version_id', $version->id)->where('trigger_event_key', $key)->first();
    }

    public function runCountToday(Organisation $organisation, Brand $brand, AutomationRuleVersion $version): int
    {
        return AutomationRun::query()->where('organisation_id', $organisation->id)->where('brand_id', $brand->id)
            ->where('automation_rule_version_id', $version->id)->where('created_at', '>=', now('UTC')->startOfDay())->count();
    }

    public function createRun(Organisation $organisation, Brand $brand, AutomationRuleVersion $version, string $key, array $report): AutomationRun
    {
        $row = new AutomationRun;
        $row->forceFill(['uuid' => (string) Str::uuid(), 'organisation_id' => $organisation->id, 'brand_id' => $brand->id,
            'automation_rule_version_id' => $version->id, 'trigger_event_key' => $key, 'status' => 'COMPLETED',
            'started_at' => now(), 'finished_at' => now(), 'result_summary' => json_encode($report, JSON_THROW_ON_ERROR)])->save();

        return $row;
    }

    public function runs(Organisation $organisation, Brand $brand): Collection
    {
        return AutomationRun::query()->where('organisation_id',$organisation->id)->where('brand_id',$brand->id)
            ->orderByDesc('id')->limit(30)->get();
    }
}
