<?php

namespace Modules\Unipotence\Domain\Data;

final readonly class UnipotenceOutcome
{
    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public int $status,
        public array $body,
        public array $headers,
        public bool $replayable,
    ) {}
}
