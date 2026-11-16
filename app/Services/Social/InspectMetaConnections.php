<?php

namespace App\Services\Social;

use App\Repository\Contracts\MetaCapabilityInspectionRepositoryContract;
use App\Social\Providers\MetaAccessTokenInspector;
use Throwable;

/** Bounded background work; results are counts, never user identifiers or credentials. */
final class InspectMetaConnections
{
    public function __construct(
        private readonly MetaCapabilityInspectionRepositoryContract $repository,
        private readonly MetaAccessTokenInspector $inspector,
    ) {}

    public function run(int $limit = 20, int $minutes = 60): array
    {
        $results = ['checked' => 0, 'verified' => 0, 'restricted' => 0, 'deferred' => 0, 'skipped' => 0];
        foreach ($this->repository->dueIds($limit, $minutes) as $id) {
            $lease = $this->repository->acquire($id, $minutes);
            if (! $lease) {
                $results['skipped']++;

                continue;
            }
            $results['checked']++;
            try {
                $result = $this->inspector->inspect($lease->accessToken);
                if ($this->repository->complete($lease, $result)) {
                    $results['verified']++;
                } else {
                    $results['restricted']++;
                }
            } catch (Throwable) {
                // Never include provider exception text; it may contain OAuth credentials.
                $this->repository->fail($lease);
                $results['deferred']++;
            }
        }

        return $results;
    }
}
