<?php

namespace App\Services\Publishing;

use App\Domain\Content\ProviderPublishCapabilityMatrix;
use App\Domain\Publishing\Delivery\SimulationHarness;
use App\Models\PublicationSandboxRun;
use App\Repository\Contracts\PublicationSandboxRepositoryContract;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** Deliberately isolated from live publication state, token vault and remote HTTP. */
final class RunPublicationSandbox
{
    public function __construct(private readonly PublicationSandboxRepositoryContract $repo) {}

    public function enqueue(int $org, int $brand, int $jobId, string $key, string $provider, string $scenario): array
    {
        if (! preg_match('/^[A-Za-z0-9:_-]{8,128}$/', $key)) {
            throw new \InvalidArgumentException('Invalid operation key');
        }
        if (ProviderPublishCapabilityMatrix::capabilityFor($provider) === null || ! in_array($scenario, SimulationHarness::SCENARIOS, true)) {
            throw new \InvalidArgumentException('Unsupported simulation');
        }

        return DB::transaction(function () use ($org, $brand, $jobId, $key, $provider, $scenario) {
            $job = $this->repo->heldJob($org, $brand, $jobId);
            $existing = $this->repo->findOperation($org, $jobId, $key);
            if ($existing) {
                if ($existing->provider !== $provider || $existing->scenario !== $scenario) {
                    throw new ConflictHttpException('Operation key reused with different simulation parameters');
                }

return $this->present($existing);
            }
            if ($job->state !== 'AWAITING_USER') {
                throw new ConflictHttpException('Only held publication jobs may be sandbox tested');
            }
            $run = $this->repo->queue($job, $key, $provider, $scenario);
            $this->repo->event($run, 'QUEUED', ['provider' => $provider, 'scenario' => $scenario]);

            return $this->present($run);
        }, 3);
    }

    public function process(int $limit = 20): array
    {
        $done = 0;
        $failed = 0;
        for ($i = 0; $i < max(0, min($limit, 100)); $i++) {
            $token = bin2hex(random_bytes(16));
            $claim = DB::transaction(function () use ($token) {
                $run = $this->repo->findNext();
                if (! $run) {
                    return null;
                }
                if ($run->status === 'LEASED') { // Previous worker may have completed simulation before its lease expired: quarantine, never replay.
                    $run->forceFill(['status' => 'UNCERTAIN', 'lease_token' => null, 'lease_until' => null])->save();
                    $this->repo->event($run, 'STALE_LEASE_QUARANTINED');

                    return ['quarantined' => true];
                }
                $run->forceFill(['status' => 'LEASED', 'lease_token' => $token, 'lease_until' => now()->addMinutes(2), 'attempt_count' => (int) $run->attempt_count + 1])->save();
                $this->repo->event($run, 'LEASED');

                return ['id' => $run->id, 'token' => $token, 'provider' => $run->provider, 'scenario' => $run->scenario];
            }, 3);
            if (! $claim) {
                break;
            }
            if (isset($claim['quarantined'])) {
                $failed++;

                continue;
            }
            // Pure deterministic offline simulation, no network, credentials or live job mutation.
            $result = null;
            try {
                $result = SimulationHarness::run($claim['provider'], $claim['scenario']);
            } catch (\Throwable) {/* sanitize errors */
            }
            DB::transaction(function () use ($claim, $result, &$done, &$failed) {
                $run = $this->repo->lockRun($claim['id']);
                if (! $run || $run->status !== 'LEASED' || ! hash_equals((string) $run->lease_token, $claim['token'])) {
                    return;
                }
                if ($result === null) {
                    $run->forceFill(['status' => 'FAILED', 'outcome_code' => 'SIMULATOR_FAILURE']);
                    $failed++;
                } else {
                    $run->forceFill(['status' => 'COMPLETED', 'outcome_kind' => $result['outcome']['kind'],
                        'outcome_code' => $result['outcome']['code'], 'safe_result' => $result]);
                    $done++;
                }
                $run->lease_token = null;
                $run->lease_until = null;
                $run->save();
                $this->repo->event($run, $run->status, ['outcome_code' => $run->outcome_code]);
            }, 3);
        }

        return ['completed' => $done, 'quarantined_or_failed' => $failed, 'network_requests' => 0, 'live_publications' => 0];
    }

    public function history(int $org, int $brand, int $job, int $limit = 30): array
    {
        return $this->repo->history($org, $brand, $job, max(1, min($limit, 100)))->map(fn ($x) => $this->present($x))->all();
    }

    private function present(PublicationSandboxRun $run): array
    {
        return ['id' => $run->id, 'publication_job_id' => $run->publication_job_id, 'provider' => $run->provider,
            'scenario' => $run->scenario, 'status' => $run->status, 'outcome_kind' => $run->outcome_kind, 'outcome_code' => $run->outcome_code,
            'result' => $run->safe_result, 'created_at' => $run->created_at?->toIso8601String()];
    }
}
