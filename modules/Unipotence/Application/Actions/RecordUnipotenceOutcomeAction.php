<?php

namespace Modules\Unipotence\Application\Actions;

use Modules\Unipotence\Application\UnipotenceGate;
use Modules\Unipotence\Domain\Data\UnipotenceClaim;
use Modules\Unipotence\Domain\Data\UnipotenceOutcome;
use Modules\Unipotence\Domain\Enums\OutcomeDisposition;
use Psr\Log\LoggerInterface;

final class RecordUnipotenceOutcomeAction
{
    public function __construct(
        private readonly UnipotenceGate $gate,
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(UnipotenceClaim $claim, UnipotenceOutcome $outcome): void
    {
        $disposition = $this->gate->recordOutcome($claim, $outcome);

        if ($disposition === OutcomeDisposition::Indeterminate) {
            $this->logger->warning('Unipotence outcome became indeterminate.', [
                'scope' => $claim->context->scope,
                'key_hash' => hash('sha256', $claim->context->key),
                'reference_hash' => $claim->context->reference === null
                    ? null
                    : hash('sha256', $claim->context->reference),
                'response_status' => $outcome->status,
                'reason' => 'response_policy',
            ]);
        }
    }
}
