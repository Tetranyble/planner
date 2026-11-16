<?php

namespace App\Services\Analytics;

use App\Authorization\BrandPermissionGuard;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\AuditEventRepositoryContract;
use App\Repository\Contracts\ProviderMetricSyncRepositoryContract;
use App\Repository\Contracts\SocialMetricRepositoryContract;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** Imported values are assertions by an operator, never verified provider-API observations. */
final class ManageSocialMetricSnapshots
{
    public function __construct(private readonly BrandPermissionGuard $guard,
        private readonly SocialMetricRepositoryContract $repo, private readonly AuditEventRepositoryContract $audit,
        private readonly ProviderMetricSyncRepositoryContract $providerMetrics) {}

    private function authorize(User $user, Organisation $org, Brand $brand, string $permission): void
    {
        if ((int) $brand->organisation_id !== (int) $org->id || ! $this->guard->allows($user, $brand, $permission)) {
            throw new AuthorizationException('Social metric access denied.');
        }
    }

    public function submit(User $user, Organisation $org, Brand $brand, array $input): array
    {
        $this->authorize($user, $org, $brand, 'approval_request.update');
        $fields = ['provider', 'account_reference', 'external_post_reference', 'observed_at', 'impressions', 'reach', 'likes', 'comments', 'shares', 'clicks', 'source_type', 'source_reference'];
        $payload = [];
        foreach ($fields as $field) {
            $payload[$field] = $input[$field] ?? (in_array($field, ['impressions', 'reach', 'likes', 'comments', 'shares', 'clicks'], true) ? 0 : null);
        }
        if (! preg_match('/(?:Z|[+-]\d{2}:\d{2})$/', $payload['observed_at'])) {
            throw ValidationException::withMessages(['observed_at' => 'An explicit timezone offset is required.']);
        }
        $observed = CarbonImmutable::parse($payload['observed_at'])->utc();
        if ($observed->isFuture() || $observed->lessThan(now()->subYears(3))) {
            throw ValidationException::withMessages(['observed_at' => 'Observation must be in the last three years and cannot be in the future.']);
        }
        $payload['observed_at'] = $observed->format('Y-m-d H:i:s');
        $key = hash('sha256', $input['submission_key']);
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($user, $org, $brand, $payload, $key, $hash) {
            Organisation::query()->whereKey($org->id)->lockForUpdate()->firstOrFail();
            $locked = Brand::query()->where('organisation_id', $org->id)->whereKey($brand->id)->lockForUpdate()->firstOrFail();
            $this->authorize($user, $org, $locked, 'approval_request.update');
            $existing = $this->repo->findSubmission($org, $locked, $key);
            if ($existing) {
                if (! hash_equals($existing->payload_hash, $hash)) {
                    throw new ConflictHttpException('Submission key reused for different metrics.');
                }

                return ['id' => $existing->id, 'created' => false, 'verification' => 'USER_REPORTED_UNVERIFIED'];
            }
            $snapshot = $this->repo->store($org, $locked, $user->id, $payload + ['submission_key' => $key, 'payload_hash' => $hash]);
            $this->audit->record($org, $user, 'social_metrics.manual_submitted', 'social_metric_snapshot', (string) $snapshot->id, $locked,
                ['provider' => $payload['provider'], 'source_type' => $payload['source_type'], 'payload_hash' => $hash]);

            return ['id' => $snapshot->id, 'created' => true, 'verification' => 'USER_REPORTED_UNVERIFIED'];
        }, 3);
    }

    public function report(User $user, Organisation $org, Brand $brand, string $from, string $to): array
    {
        $this->authorize($user, $org, $brand, 'approval_request.index');
        try {
            $start = CarbonImmutable::createFromFormat('!Y-m-d', $from, 'UTC');
            $end = CarbonImmutable::createFromFormat('!Y-m-d', $to, 'UTC');
        } catch (\Throwable) {
            throw ValidationException::withMessages(['dates' => 'Invalid date range.']);
        }
        if (! $start || ! $end || $start->format('Y-m-d') !== $from || $end->format('Y-m-d') !== $to ||
            $end->lt($start) || $start->diffInDays($end) > 90) {
            throw ValidationException::withMessages(['dates' => 'Select an inclusive UTC range of 90 days or fewer.']);
        }
        $fromDate = $start->format('Y-m-d H:i:s');
        $until = $end->addDay()->format('Y-m-d H:i:s');
        $manual = $this->repo->report($org, $brand, $fromDate, $until);

        // Never combine operator-provided counts with provider API observations.
        return ['from' => $from, 'to' => $to, 'timezone' => 'UTC'] + $manual +
            ['provider_api' => $this->providerMetrics->report($org,$brand,$fromDate,$until)];
    }
}
