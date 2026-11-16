<?php

namespace Modules\Unipotence\Domain\Data;

final readonly class UnipotenceContext
{
    public function __construct(
        public string $scope,
        public string $key,
        public string $requestHash,
        public ?string $reference,
    ) {}
}
