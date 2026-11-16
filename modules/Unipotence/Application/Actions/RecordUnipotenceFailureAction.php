<?php

namespace Modules\Unipotence\Application\Actions;

use Modules\Unipotence\Application\UnipotenceGate;
use Modules\Unipotence\Domain\Contracts\FailureClassifier;
use Modules\Unipotence\Domain\Data\UnipotenceClaim;
use Psr\Log\LoggerInterface;
use Throwable;

final class RecordUnipotenceFailureAction
{
    public function __construct(
        private readonly UnipotenceGate $gate,
        private readonly FailureClassifier $failureClassifier,
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(UnipotenceClaim $claim, Throwable $exception): void
    {
        try {
            if ($this->failureClassifier->isSafeToRetry($exception)) {
                $this->gate->release($claim);

                return;
            }

            $this->gate->markFailureIndeterminate($claim);
            $this->logger->warning('Unipotence outcome became indeterminate.', [
                'scope' => $claim->context->scope,
                'key_hash' => hash('sha256', $claim->context->key),
                'reference_hash' => $claim->context->reference === null
                    ? null
                    : hash('sha256', $claim->context->reference),
                'exception_class' => $exception::class,
                'reason' => 'possibly_dispatched_exception',
            ]);
        } catch (Throwable $persistenceFailure) {
            $this->logger->critical('Failed to persist an unipotence failure outcome.', [
                'scope' => $claim->context->scope,
                'key_hash' => hash('sha256', $claim->context->key),
                'original_exception' => $exception,
                'persistence_exception' => $persistenceFailure,
            ]);
        }
    }
}
