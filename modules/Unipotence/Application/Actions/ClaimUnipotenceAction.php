<?php

namespace Modules\Unipotence\Application\Actions;

use Modules\Unipotence\Application\UnipotenceGate;
use Modules\Unipotence\Domain\Data\UnipotenceClaim;
use Modules\Unipotence\Domain\Data\UnipotenceContext;

final class ClaimUnipotenceAction
{
    public function __construct(
        private readonly UnipotenceGate $gate,
    ) {}

    public function execute(UnipotenceContext $context): UnipotenceClaim
    {
        return $this->gate->claim($context);
    }
}
