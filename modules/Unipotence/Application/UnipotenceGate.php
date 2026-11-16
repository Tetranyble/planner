<?php

namespace Modules\Unipotence\Application;

use Modules\Unipotence\Domain\Contracts\UnipotenceRepository;
use Modules\Unipotence\Domain\Data\UnipotenceClaim;
use Modules\Unipotence\Domain\Data\UnipotenceContext;
use Modules\Unipotence\Domain\Data\UnipotenceOutcome;
use Modules\Unipotence\Domain\Enums\OutcomeDisposition;
use Modules\Unipotence\Domain\Services\OutcomePolicy;

final class UnipotenceGate
{
    public function __construct(
        private readonly UnipotenceRepository $repository,
        private readonly OutcomePolicy $outcomePolicy,
        private readonly int $leaseSeconds,
    ) {}

    public function claim(UnipotenceContext $context): UnipotenceClaim
    {
        return new UnipotenceClaim(
            context: $context,
            token: $this->repository->claim(
                $context->scope,
                $context->key,
                $context->requestHash,
                $context->reference,
                $this->leaseSeconds,
            ),
        );
    }

    public function recordOutcome(UnipotenceClaim $claim, UnipotenceOutcome $outcome): OutcomeDisposition
    {
        $disposition = $this->outcomePolicy->decide($outcome);

        match ($disposition) {
            OutcomeDisposition::Complete => $this->complete($claim, $outcome),
            OutcomeDisposition::Release => $this->release($claim),
            OutcomeDisposition::Indeterminate => $this->markIndeterminate($claim, $outcome),
        };

        return $disposition;
    }

    public function release(UnipotenceClaim $claim): void
    {
        $this->repository->release(
            $claim->context->scope,
            $claim->context->key,
            $claim->token,
        );
    }

    public function markFailureIndeterminate(UnipotenceClaim $claim): void
    {
        $this->repository->markIndeterminate(
            $claim->context->scope,
            $claim->context->key,
            $claim->context->requestHash,
            $claim->token,
            null,
            ['message' => 'The original request outcome is unknown.'],
        );
    }

    /**
     * @param  array<string, mixed>  $responseBody
     * @param  array<string, string>  $responseHeaders
     */
    public function reconcile(
        string $scope,
        string $reference,
        int $responseStatus,
        array $responseBody,
        array $responseHeaders,
    ): int {
        return $this->repository->completeIndeterminateByReference(
            $scope,
            $reference,
            $responseStatus,
            $responseBody,
            $responseHeaders,
        );
    }

    private function complete(UnipotenceClaim $claim, UnipotenceOutcome $outcome): void
    {
        $this->repository->complete(
            $claim->context->scope,
            $claim->context->key,
            $claim->context->requestHash,
            $claim->token,
            $outcome->status,
            $outcome->body,
            $outcome->headers,
        );
    }

    private function markIndeterminate(UnipotenceClaim $claim, UnipotenceOutcome $outcome): void
    {
        $this->repository->markIndeterminate(
            $claim->context->scope,
            $claim->context->key,
            $claim->context->requestHash,
            $claim->token,
            $outcome->status,
            $outcome->body,
            $outcome->headers,
        );
    }
}
