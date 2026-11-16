<?php

namespace Modules\Unipotence\Domain\Data;

final readonly class UnipotenceClaim
{
    public function __construct(
        public UnipotenceContext $context,
        public string $token,
    ) {}
}
