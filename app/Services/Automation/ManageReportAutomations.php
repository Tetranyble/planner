<?php

namespace App\Services\Automation;

use App\Authorization\BrandPermissionGuard;
use App\Models\AutomationRule;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\AuditEventRepositoryContract;
use App\Repository\Contracts\ProviderMetricSyncRepositoryContract;
use App\Repository\Contracts\ReportAutomationRepositoryContract;
use App\Repository\Contracts\SocialMetricRepositoryContract;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** Analytics reports only: manual or opted-in daily UTC triggers. No external actions. */
final class ManageReportAutomations
{
    public function __construct(private readonly BrandPermissionGuard $guard,
        private readonly ReportAutomationRepositoryContract $repo,
        private readonly SocialMetricRepositoryContract $metrics,
        private readonly ProviderMetricSyncRepositoryContract $providerMetrics,
        private readonly AuditEventRepositoryContract $audit) {}

    private function authorize(User $actor, Organisation $org, Brand $brand, bool $write): void
    {
        if ((int) $brand->organisation_id !== (int) $org->id ||
            ! $this->guard->allows($actor, $brand, $write ? 'approval_request.update' : 'approval_request.index')) {
            throw new AuthorizationException('Report automation access denied.');
        }
    }

    private function dates(string $from, string $to): void
    {
        try {
            $start = CarbonImmutable::createFromFormat('!Y-m-d', $from, 'UTC');
            $end = CarbonImmutable::createFromFormat('!Y-m-d', $to, 'UTC');
        } catch (\Throwable) {
            throw ValidationException::withMessages(['dates' => 'Invalid UTC dates.']);
        }
        if (! $start || ! $end || $start->format('Y-m-d') !== $from || $end->format('Y-m-d') !== $to ||
            $end->lt($start) || $start->diffInDays($end) > 90 || $end->isFuture()) {
            throw ValidationException::withMessages(['dates' => 'Select a completed UTC range of at most 90 days.']);
        }
    }

    public function index(User $actor, Organisation $org, Brand $brand): array
    {
        $this->authorize($actor, $org, $brand, false);

        return ['rules' => $this->repo->rules($org, $brand)->map(fn ($r) => [
            'id' => $r->id, 'name' => $r->name, 'status' => $r->status, 'max_runs_per_day' => $r->max_runs_per_day, 'trigger_kind' => $r->trigger_kind,
            'schedule' => $this->scheduleDisplay($org, $brand, $r)])->all(),
            'runs' => $this->repo->runs($org, $brand)->map(function ($r) {
                $report = json_decode($r->result_summary ?? '{}', true) ?: [];

                return ['id' => $r->id, 'uuid' => $r->uuid, 'status' => $r->status, 'created_at' => $r->created_at?->toIso8601String(),
                    'source' => $report['verification'] ?? 'UNKNOWN',
                    'provider_api' => $report['provider_api'] ?? null, 'aggregates_complete' => $report['aggregates_complete'] ?? false,
                    'totals' => $report['totals'] ?? null, 'rule_version_id' => $r->automation_rule_version_id];
            })->all()];
    }

    private function scheduleDisplay(Organisation $org, Brand $brand, AutomationRule $rule): ?array
    {
        if ($rule->trigger_kind !== 'DAILY_UTC') {
            return null;
        }
        $version = $this->repo->latestVersion($org, $brand, $rule);
        if (! $version) {
            return null;
        }
        $config = json_decode($version->trigger_config, true) ?: [];
        $condition = json_decode($version->condition_config, true) ?: [];

        return ['hour_utc' => (int) ($config['hour_utc'] ?? 0), 'lookback_days' => (int) ($condition['lookback_days'] ?? 1),
            'source' => 'USER_REPORTED_UNVERIFIED'];
    }

    private function validateConfig(array $input): void
    {
        $kind = $input['trigger_kind'] ?? 'MANUAL';
        if ($kind === 'DAILY_UTC') {
            if (! isset($input['hour_utc'],$input['lookback_days']) || ! is_int($input['hour_utc']) ||
                $input['hour_utc'] < 0 || $input['hour_utc'] > 23 || ! is_int($input['lookback_days']) ||
                $input['lookback_days'] < 1 || $input['lookback_days'] > 30) {
                throw ValidationException::withMessages(['schedule' => 'Daily schedules require UTC hour 0–23 and lookback 1–30 days.']);
            }
        } elseif ($kind === 'MANUAL') {
            $this->dates($input['from'] ?? '', $input['to'] ?? '');
        } else {
            throw ValidationException::withMessages(['trigger_kind' => 'Unsupported report trigger.']);
        }
    }

    public function create(User $actor, Organisation $org, Brand $brand, array $input): array
    {
        $this->authorize($actor, $org, $brand, true);
        $this->validateConfig($input);

        return DB::transaction(function () use ($actor, $org, $brand, $input) {
            $locked = Brand::query()->where('organisation_id', $org->id)->whereKey($brand->id)->lockForUpdate()->firstOrFail();
            $this->authorize($actor, $org, $locked, true);
            $rule = $this->repo->createRule($org, $locked, $actor->id, $input['name'], $input['max_runs_per_day']);
            $hash = hash('sha256', json_encode(['trigger_kind' => $input['trigger_kind'] ?? 'MANUAL', 'from' => $input['from'] ?? null,
                'to' => $input['to'] ?? null, 'hour_utc' => $input['hour_utc'] ?? null, 'lookback_days' => $input['lookback_days'] ?? null,
                'action' => 'METRICS_REPORT_ONLY'], JSON_THROW_ON_ERROR));
            $this->repo->createVersion($org, $locked, $rule, $actor->id, $input, $hash);
            $this->audit->record($org, $actor, 'automation.report_rule_created', 'automation_rule', (string) $rule->id, $locked, ['config_hash' => $hash]);

            return ['id' => $rule->id, 'status' => 'DRAFT'];
        }, 3);
    }

    public function status(User $actor, Organisation $org, Brand $brand, int $id, string $status): array
    {
        $this->authorize($actor, $org, $brand, true);

        return DB::transaction(function () use ($actor, $org, $brand, $id, $status) {
            $locked = Brand::query()->where('organisation_id', $org->id)->whereKey($brand->id)->lockForUpdate()->firstOrFail();
            $rule = $this->repo->rule($org, $locked, $id, true);
            if (! $rule) {
                abort(404);
            }
            $this->authorize($actor, $org, $locked, true);
            if ($rule->status === 'RETIRED') {
                throw new ConflictHttpException('Retired rules are immutable.');
            }
            $this->repo->setStatus($rule, $status);
            $this->audit->record($org, $actor, 'automation.report_rule_status', 'automation_rule', (string) $id, $locked, ['status' => $status]);

            return ['id' => $id, 'status' => $status];
        }, 3);
    }

    public function execute(User $actor, Organisation $org, Brand $brand, int $id, string $rawKey): array
    {
        $this->authorize($actor, $org, $brand, true);
        $key = hash('sha256', $rawKey);

        return DB::transaction(function () use ($actor, $org, $brand, $id, $key) {
            $locked = Brand::query()->where('organisation_id', $org->id)->whereKey($brand->id)->lockForUpdate()->firstOrFail();
            $this->authorize($actor, $org, $locked, true);
            $rule = $this->repo->rule($org, $locked, $id, true);
            if (! $rule) {
                abort(404);
            }
            $version = $this->repo->latestVersion($org, $locked, $rule);
            if (! $version) {
                throw new ConflictHttpException('Rule has no version.');
            }
            $existing = $this->repo->run($org, $locked, $version, $key);
            if ($existing) {
                return ['id' => $existing->id, 'created' => false, 'status' => $existing->status];
            }
            if ($rule->status !== 'ENABLED') {
                throw new ConflictHttpException('Enable rule before running.');
            }
            if ($this->repo->runCountToday($org, $locked, $version) >= (int) $rule->max_runs_per_day) {
                throw ValidationException::withMessages(['limit' => 'Daily report run limit reached.']);
            }
            $condition = json_decode($version->condition_config, true, 512, JSON_THROW_ON_ERROR);
            $action = json_decode($version->action_plan, true, 512, JSON_THROW_ON_ERROR);
            if (($action['kind'] ?? null) !== 'METRICS_REPORT_ONLY') {
                throw new ConflictHttpException('Unsupported action; no external actions permitted.');
            }
            if ($rule->trigger_kind !== 'MANUAL') {
                throw new ConflictHttpException('Daily rules are executed by the scheduler only.');
            }
            $this->dates($condition['from'], $condition['to']);
            $start = $condition['from'].' 00:00:00';
            $end = CarbonImmutable::parse($condition['to'], 'UTC')->addDay()->format('Y-m-d').' 00:00:00';
            $report = $this->metrics->report($org, $locked, $start, $end);
            $report['provider_api'] = $this->providerMetrics->report($org, $locked, $start, $end);
            $report['automation_rule_id'] = $rule->id;
            $report['rule_version_hash'] = $version->config_hash;
            $report['generated_at'] = now('UTC')->toIso8601String();
            $run = $this->repo->createRun($org, $locked, $version, $key, $report);
            $this->audit->record($org, $actor, 'automation.manual_report_completed', 'automation_run', (string) $run->id, $locked,
                ['rule_id' => $rule->id, 'verification' => $report['verification'], 'aggregates_complete' => $report['aggregates_complete']]);

            return ['id' => $run->id, 'created' => true, 'status' => 'COMPLETED', 'verification' => $report['verification']];
        }, 3);
    }

    /** Runs only explicitly enabled DAILY_UTC rules. Called by a single-server scheduler command. */
    public function processDue(int $limit = 25, bool $dryRun = true): array
    {
        $clock = CarbonImmutable::now('UTC');
        $result = ['examined' => 0, 'created' => 0, 'skipped' => 0, 'dry_run' => $dryRun, 'errors' => 0];
        foreach ($this->repo->scheduledCandidates(max(1, min($limit, 100))) as $candidate) {
            $result['examined']++;
            try {
                $created = DB::transaction(function () use ($candidate, $clock, $dryRun) {
                    $brand = Brand::query()->where('organisation_id', $candidate->organisation_id)->whereKey($candidate->brand_id)
                        ->lockForUpdate()->first();
                    if (! $brand) {
                        return false;
                    }
                    $rule = $this->repo->rule(Organisation::query()->findOrFail($candidate->organisation_id), $brand, $candidate->id, true);
                    if (! $rule || $rule->status !== 'ENABLED' || $rule->trigger_kind !== 'DAILY_UTC') {
                        return false;
                    }
                    $org = Organisation::query()->findOrFail($candidate->organisation_id);
                    $version = $this->repo->latestVersion($org, $brand, $rule);
                    if (! $version) {
                        return false;
                    }
                    $trigger = json_decode($version->trigger_config, true, 512, JSON_THROW_ON_ERROR);
                    $condition = json_decode($version->condition_config, true, 512, JSON_THROW_ON_ERROR);
                    $action = json_decode($version->action_plan, true, 512, JSON_THROW_ON_ERROR);
                    if (($trigger['kind'] ?? null) !== 'DAILY_UTC' || ($action['kind'] ?? null) !== 'METRICS_REPORT_ONLY') {
                        return false;
                    }
                    $hour = $trigger['hour_utc'] ?? -1;
                    $days = $condition['lookback_days'] ?? 0;
                    if (! is_int($hour) || $hour < 0 || $hour > 23 || ! is_int($days) || $days < 1 || $days > 30 || $clock->hour < $hour) {
                        return false;
                    }
                    $key = hash('sha256', 'daily-report:'.$clock->toDateString().':'.$version->id);
                    if ($this->repo->run($org, $brand, $version, $key) ||
                        $this->repo->runCountToday($org, $brand, $version) >= (int) $rule->max_runs_per_day) {
                        return false;
                    }
                    if ($dryRun) {
                        return true;
                    }
                    // Completed UTC days only; end bound is exclusive.
                    $end = $clock->startOfDay();
                    $start = $end->subDays($days);
                    $report = $this->metrics->report($org, $brand, $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'));
                    $report['provider_api'] = $this->providerMetrics->report($org, $brand,
                        $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'));
                    $report['automation_rule_id'] = $rule->id;
                    $report['rule_version_hash'] = $version->config_hash;
                    $report['trigger_kind'] = 'DAILY_UTC';
                    $report['generated_at'] = $clock->toIso8601String();
                    $report['report_window_utc'] = ['from' => $start->toDateString(), 'to_exclusive' => $end->toDateString()];
                    $run = $this->repo->createRun($org,$brand,$version,$key,$report);
                    // Scheduler is an application actor, not the report owner impersonated as a human.
                    $run->forceFill(['error_code' => null])->save();

                    return true;
                }, 3);
                if ($created) {
                    $result['created']++;
                } else {
                    ++$result['skipped'];
                }
            } catch (\Throwable $e) {
                $result['errors']++;
                report($e);
            }
        }

        return $result;
    }
}
