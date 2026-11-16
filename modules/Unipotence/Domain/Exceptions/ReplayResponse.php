<?php

namespace Modules\Unipotence\Domain\Exceptions;

use RuntimeException;

final class ReplayResponse extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $responseBody
     * @param  array<string, string>  $responseHeaders
     */
    public function __construct(
        public readonly int $responseStatus,
        public readonly array $responseBody,
        public readonly array $responseHeaders,
    ) {
        parent::__construct('The unipotent response is being replayed.');
    }
}
