<?php

namespace Modules\Unipotence\Application\Actions;

use Modules\Unipotence\Application\UnipotenceGate;
use Modules\Unipotence\Domain\Data\UnipotenceOutcome;

final class ReconcileIndeterminateUnipotenceAction
{
    public function __construct(
        private readonly UnipotenceGate $gate,
    ) {}

    public function execute(
        string $scope,
        string $reference,
        UnipotenceOutcome $outcome,
    ): int {
        if (! $outcome->replayable) {
            return 0;
        }

        return $this->gate->reconcile(
            $scope,
            $reference,
            $outcome->status,
            $outcome->body,
            $outcome->headers,
        );
    }
}
