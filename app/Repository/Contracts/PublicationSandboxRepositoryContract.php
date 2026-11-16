<?php

namespace App\Repository\Contracts;

use App\Models\PublicationJob;
use App\Models\PublicationSandboxRun;
use Illuminate\Support\Collection;

interface PublicationSandboxRepositoryContract
{
    public function heldJob(int $org, int $brand, int $job): PublicationJob;

    public function findOperation(int $org, int $job, string $key): ?PublicationSandboxRun;

    public function queue(PublicationJob $job, string $key, string $provider, string $scenario): PublicationSandboxRun;

    public function findNext(): ?PublicationSandboxRun;

    public function lockRun(int $id): ?PublicationSandboxRun;

    public function event(PublicationSandboxRun $run, string $name, array $details = []): void;

    public function history(int $org, int $brand, int $job, int $limit): Collection;
}
