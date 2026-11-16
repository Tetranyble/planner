<?php

namespace App\Repository\Contracts;

use App\Models\AutomationRule;
use App\Models\AutomationRuleVersion;
use App\Models\AutomationRun;
use App\Models\Brand;
use App\Models\Organisation;
use Illuminate\Support\Collection;

interface ReportAutomationRepositoryContract
{
    public function scheduledCandidates(int $limit): Collection;

    public function rules(Organisation $organisation, Brand $brand): Collection;

    public function rule(Organisation $organisation, Brand $brand, int $id, bool $lock = false): ?AutomationRule;

    public function createRule(Organisation $organisation, Brand $brand, int $actorId, string $name, int $dailyLimit): AutomationRule;

    public function createVersion(Organisation $organisation, Brand $brand, AutomationRule $rule, int $actorId, array $config, string $hash): AutomationRuleVersion;

    public function setStatus(AutomationRule $rule, string $status): void;

    public function latestVersion(Organisation $organisation, Brand $brand, AutomationRule $rule): ?AutomationRuleVersion;

    public function run(Organisation $organisation, Brand $brand, AutomationRuleVersion $version, string $key): ?AutomationRun;

    public function runCountToday(Organisation $organisation, Brand $brand, AutomationRuleVersion $version): int;

    public function createRun(Organisation $organisation, Brand $brand, AutomationRuleVersion $version, string $key, array $report): AutomationRun;

    public function runs(Organisation $organisation,Brand $brand): Collection;
}
